<?php

declare(strict_types=1);

namespace Lemonade\Framework\Event\Config;

final class EventsConfig
{
    /**
     * @param array<string, list<callable|string>> $listeners
     */
    public function __construct(
        public readonly array $listeners,
    ) {}
}
