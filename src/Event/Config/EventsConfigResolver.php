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
                    if (is_string($handler) || is_callable($handler)) {
                        $listeners[$eventClass][] = $handler;
                    }
                }
            }
        }

        return new EventsConfig($listeners);
    }
}
