<?php

declare(strict_types=1);

namespace Lemonade\Framework\Event\Config;

use Lemonade\Framework\Event\EventListenerDefinition;

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
                $listeners[$eventClass] ??= [];

                foreach ($handlerList as $handler) {
                    $normalized = $this->normalizeListener($handler);
                    if ($normalized !== null) {
                        $listeners[$eventClass][] = $normalized;
                    }
                }
            }
        }

        $definitions = [];
        $order = 0;
        foreach ($listeners as $eventClass => $handlers) {
            foreach ($handlers as $handler) {
                /** @var class-string $eventClass */
                $definitions[] = new EventListenerDefinition($eventClass, $handler['listener'], $handler['method'], $handler['priority'], $order++);
            }
        }
        return new EventsConfig($listeners, $definitions);
    }

    /**
     * @return array{listener:string,method:string,priority:int}|null
     */
    private function normalizeListener(mixed $listener): ?array
    {
        if (is_array($listener) && is_string($listener['listener'] ?? null)) {
            return ['listener' => $listener['listener'], 'method' => is_string($listener['method'] ?? null) ? $listener['method'] : '__invoke', 'priority' => is_int($listener['priority'] ?? null) ? $listener['priority'] : 0];
        }
        return null;
    }
}
