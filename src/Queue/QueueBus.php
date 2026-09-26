<?php

declare(strict_types=1);

namespace Lemonade\Framework\Queue;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Queue\Exception\QueueHandlerFailureException;

final class QueueBus implements QueueBusInterface
{
    private readonly JobHandlerRegistry $handlers;

    /**
     * @param array<string, QueueTransportInterface> $transports
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly array $transports,
        private readonly string $defaultTransport = 'sync',
        ?JobHandlerRegistry $handlers = null,
    ) {
        $this->handlers = $handlers ?? new JobHandlerRegistry();
    }

    public function dispatch(object $message, ?string $transport = null, string $queue = 'default', int $delaySeconds = 0): void
    {
        $transportName = $transport ?? $this->defaultTransport;
        $resolved = $this->transport($transportName);

        if ($transportName === 'sync') {
            $this->handle(new QueuedMessage($message, $queue), $transportName);

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
            $this->handle($item, $transportName);
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

    private function handle(QueuedMessage $item, string $transport): void
    {
        $context = JobContext::fromQueuedMessage($item, $transport);
        $handler = $this->handlers->handlerFor($context->message);
        $resolved = $this->resolveCallable($handler);
        $resolved($context->message);
    }

    /**
     * @param (callable(object): void)|string $handler
     * @return callable(object):void
     */
    private function resolveCallable(callable|string $handler): callable
    {
        if (is_callable($handler)) {
            return static function (object $message) use ($handler): void {
                $handler($message);
            };
        }

        $resolved = $this->container->get($handler);

        if (is_callable($resolved)) {
            return static function (object $message) use ($resolved): void {
                $resolved($message);
            };
        }

        if (is_object($resolved) && method_exists($resolved, '__invoke')) {
            return static function (object $message) use ($resolved): void {
                $resolved($message);
            };
        }

        throw new \RuntimeException(sprintf('Queue handler "%s" is not callable.', $handler));
    }

    private function transport(string $name): QueueTransportInterface
    {
        if (!isset($this->transports[$name])) {
            throw new \RuntimeException(sprintf('Queue transport "%s" is not configured.', $name));
        }

        return $this->transports[$name];
    }
}
