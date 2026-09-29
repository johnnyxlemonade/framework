<?php

declare(strict_types=1);

namespace Lemonade\Framework\Event;

use LogicException;

final class EventListenerRegistry
{
    /** @var list<EventListenerDefinition> */
    private array $definitions = [];
    private bool $frozen = false;
    private int $nextOrder = 0;

    public function add(EventListenerDefinition $definition): void
    {
        if ($this->frozen) {
            throw new LogicException('Event listener registry is frozen.');
        }
        $this->definitions[] = new EventListenerDefinition($definition->eventClass, $definition->listenerClass, $definition->method, $definition->priority, $this->nextOrder++);
    }

    public function freeze(): void
    {
        $this->frozen = true;
    }

    /** @return list<EventListenerDefinition> */
    public function forEachDefinition(): array
    {
        return $this->definitions;
    }

    /** @return list<EventListenerDefinition> */
    public function for(object|string $event): array
    {
        $class = is_object($event) ? $event::class : $event;
        $parents = class_parents($class);
        $interfaces = class_implements($class);
        $types = array_values(array_unique([$class, ...($parents === false ? [] : $parents), ...($interfaces === false ? [] : $interfaces)]));
        $definitions = array_values(array_filter($this->definitions, static fn(EventListenerDefinition $d): bool => in_array($d->eventClass, $types, true)));
        usort($definitions, static function (EventListenerDefinition $a, EventListenerDefinition $b): int {
            $priority = $b->priority <=> $a->priority;
            return $priority !== 0 ? $priority : $a->registrationOrder <=> $b->registrationOrder;
        });
        return $definitions;
    }
}
