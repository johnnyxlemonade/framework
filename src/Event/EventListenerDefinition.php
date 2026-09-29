<?php

declare(strict_types=1);

namespace Lemonade\Framework\Event;

use InvalidArgumentException;

final readonly class EventListenerDefinition
{
    /** @param class-string $eventClass @param class-string $listenerClass */
    public function __construct(
        public string $eventClass,
        public string $listenerClass,
        public string $method = '__invoke',
        public int $priority = 0,
        public int $registrationOrder = 0,
    ) {
        foreach (['Event class' => $eventClass, 'Listener class' => $listenerClass, 'Listener method' => $method] as $label => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException($label . ' must not be empty.');
            }
        }
    }
}
