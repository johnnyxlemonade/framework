<?php

declare(strict_types=1);

namespace Lemonade\Framework\Event;

use Lemonade\Framework\Container\ContainerInterface;

final class InMemoryEventDispatcher implements EventDispatcherInterface
{
    /**
     * @var array<string, list<array{priority:int,listener:callable|class-string}>>
     */
    private array $listeners = [];

    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    public function dispatch(object $event): object
    {
        foreach ($this->resolveListeners($event) as $listener) {
            $resolved = $this->resolveListener($listener);
            $resolved($event);
        }

        return $event;
    }

    public function addListener(string $eventClass, callable|string $listener, int $priority = 0): void
    {
        $this->listeners[$eventClass][] = [
            'priority' => $priority,
            'listener' => $listener,
        ];
    }

    /**
     * @return list<callable|class-string>
     */
    private function resolveListeners(object $event): array
    {
        $classes = array_values(array_unique(array_merge(
            [get_class($event)],
            class_parents($event),
            class_implements($event),
        )));

        $matched = [];
        foreach ($classes as $class) {
            foreach ($this->listeners[$class] ?? [] as $item) {
                $matched[] = $item;
            }
        }

        usort(
            $matched,
            static fn(array $a, array $b): int => $b['priority'] <=> $a['priority'],
        );

        return array_map(
            static fn(array $row): callable|string => $row['listener'],
            $matched,
        );
    }

    /**
     * @param callable|class-string $listener
     * @return callable(object):void
     */
    private function resolveListener(callable|string $listener): callable
    {
        if (is_callable($listener)) {
            return static function (object $event) use ($listener): void {
                $listener($event);
            };
        }

        $resolved = $this->container->get($listener);

        if (is_callable($resolved)) {
            return static function (object $event) use ($resolved): void {
                $resolved($event);
            };
        }

        if (is_object($resolved) && method_exists($resolved, '__invoke')) {
            return static function (object $event) use ($resolved): void {
                $resolved($event);
            };
        }

        throw new \RuntimeException(sprintf(
            'Event listener "%s" is not callable.',
            $listener,
        ));
    }
}
