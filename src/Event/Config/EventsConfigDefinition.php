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

    public function listener(string $eventClass, callable|string $listener): self
    {
        return $this->append("listeners.{$eventClass}", $listener);
    }
}
