<?php

declare(strict_types=1);

namespace Lemonade\Framework\Queue;

use Lemonade\Framework\Container\ContainerInterface;

final class QueueBus implements QueueBusInterface
{
    /**
     * @var array<string, callable|class-string>
     */
    private array $handlers = [];

    /**
     * @param array<string, QueueTransportInterface> $transports
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly array $transports,
        private readonly string $defaultTransport = 'sync',
    ) {}

    public function dispatch(object $message, ?string $transport = null, string $queue = 'default', int $delaySeconds = 0): void
    {
        $transportName = $transport ?? $this->defaultTransport;
        $resolved = $this->transport($transportName);

        if ($transportName === 'sync') {
            $this->handle($message);

            return;
        }

        $resolved->enqueue(new QueuedMessage($message, $queue), $delaySeconds);
    }

    public function addHandler(string $messageClass, callable|string $handler): void
    {
        $this->handlers[$messageClass] = $handler;
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
            $this->handle($item->message());
            $resolved->ack($item);

            return true;
        } catch (\Throwable $e) {
            $resolved->fail($item, $e->getMessage());

            throw $e;
        }
    }

    private function handle(object $message): void
    {
        $handler = $this->resolveHandler($message);
        $resolved = $this->resolveCallable($handler);
        $resolved($message);
    }

    /**
     * @return callable|class-string
     */
    private function resolveHandler(object $message): callable|string
    {
        $class = get_class($message);

        if (isset($this->handlers[$class])) {
            return $this->handlers[$class];
        }

        foreach (class_parents($message) as $parent) {
            if (isset($this->handlers[$parent])) {
                return $this->handlers[$parent];
            }
        }

        foreach (class_implements($message) as $iface) {
            if (isset($this->handlers[$iface])) {
                return $this->handlers[$iface];
            }
        }

        throw new \RuntimeException(sprintf('Queue handler for "%s" is not registered.', $class));
    }

    /**
     * @param callable|class-string $handler
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
