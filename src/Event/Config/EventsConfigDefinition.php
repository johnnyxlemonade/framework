<?php

declare(strict_types=1);

namespace Lemonade\Framework\Event\Config;

use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;

final class EventsConfigDefinition extends AbstractConfigDefinition
{
    public static function create(): self
    {
        return new self();
    }

    public static function moduleKey(): string
    {
        return 'events';
    }

    /**
     * @param class-string $listener
     */
    public function listener(string $eventClass, string $listener, string $method = '__invoke', int $priority = 0): self
    {
        return $this->append("listeners.{$eventClass}", ['listener' => $listener, 'method' => $method, 'priority' => $priority]);
    }
}
