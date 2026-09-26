<?php

declare(strict_types=1);

namespace Lemonade\Framework\Queue;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Container\ScopeFactoryInterface;
use Lemonade\Framework\Queue\Exception\QueueHandlerFailureException;

final class QueueBus implements QueueBusInterface
{
    private readonly JobHandlerRegistry $handlers;
    private readonly JobHandlerInvoker $invoker;

    /**
     * @param array<string, QueueTransportInterface> $transports
     */
    public function __construct(
        ContainerInterface $container,
        private readonly array $transports,
        private readonly string $defaultTransport = 'sync',
        ?JobHandlerRegistry $handlers = null,
        ?JobHandlerInvoker $invoker = null,
    ) {
        $this->handlers = $handlers ?? new JobHandlerRegistry();
        if ($invoker === null) {
            if (!$container instanceof ScopeFactoryInterface) {
                throw new \LogicException(sprintf(
                    'QueueBus container must implement %s to invoke handlers.',
                    ScopeFactoryInterface::class,
                ));
            }

            $invoker = new JobHandlerInvoker($this->handlers, $container);
        }

        $this->invoker = $invoker;
    }

    public function dispatch(object $message, ?string $transport = null, string $queue = 'default', int $delaySeconds = 0): void
    {
        $transportName = $transport ?? $this->defaultTransport;
        $resolved = $this->transport($transportName);

        if ($transportName === 'sync') {
            $this->handle(new QueuedMessage($message, $queue), $transportName, bindQueuedMessage: false);

            return;
        }

        $resolved->enqueue(new QueuedMessage($message, $queue), $delaySeconds);
    }

    /**
     * @param (callable(object): void)|string $handler
     */
    public function addHandler(string $messageClass, callable|string $handler): void
    {
        $this->handlers->register($messageClass, $handler);
    }

    public function processNext(string $queue = 'default', ?string $transport = null): bool
    {
        $transportName = $transport ?? $this->defaultTransport;
        $resolved = $this->transport($transportName);
        $item = $resolved->dequeue($queue);

        if (!$item instanceof QueuedMessage) {
            return false;
        }

        try {
            $this->handle($item, $transportName, bindQueuedMessage: true);
        } catch (\Throwable $handlerException) {
            try {
                $resolved->fail($item, $handlerException->getMessage());
            } catch (\Throwable $failException) {
                throw new QueueHandlerFailureException($handlerException, $failException);
            }

            throw $handlerException;
        }

        $resolved->ack($item);

        return true;
    }

    private function handle(QueuedMessage $item, string $transport, bool $bindQueuedMessage): void
    {
        $context = JobContext::fromQueuedMessage($item, $transport);
        $this->invoker->invoke($context, $bindQueuedMessage ? $item : null);
    }

    private function transport(string $name): QueueTransportInterface
    {
        if (!isset($this->transports[$name])) {
            throw new \RuntimeException(sprintf('Queue transport "%s" is not configured.', $name));
        }

        return $this->transports[$name];
    }
}
