<?php

declare(strict_types=1);

namespace Lemonade\Framework\Event\Config;

final class EventsConfig
{
    /**
     * @param array<string, list<(callable(object): void)|string>> $listeners
     */
    public function __construct(
        public readonly array $listeners,
    ) {}
}
