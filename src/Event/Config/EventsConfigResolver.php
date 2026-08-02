<?php

declare(strict_types=1);

namespace Lemonade\Framework\Event\Config;

final class EventsConfigResolver
{
    public function resolve(EventsConfigDefinition ...$definitions): EventsConfig
    {
        $listeners = [];

        foreach ($definitions as $definition) {
            $data = $definition->toArray();
            $rawListeners = is_array($data['listeners'] ?? null) ? $data['listeners'] : [];

            foreach ($rawListeners as $eventClass => $handlers) {
                if (!is_string($eventClass)) {
                    continue;
                }

                $handlerList = is_array($handlers) ? $handlers : [$handlers];
                $listeners[$eventClass] = [];

                foreach ($handlerList as $handler) {
                    $normalized = $this->normalizeListener($handler);
                    if ($normalized !== null) {
                        $listeners[$eventClass][] = $normalized;
                    }
                }
            }
        }

        return new EventsConfig($listeners);
    }

    /**
     * @return ((callable(object): void)|string)|null
     */
    private function normalizeListener(mixed $listener): callable|string|null
    {
        if (is_string($listener)) {
            return $listener;
        }

        if (is_callable($listener)) {
            return static function (object $event) use ($listener): void {
                $listener($event);
            };
        }

        return null;
    }
}
