<?php

declare(strict_types=1);

namespace Lemonade\Framework\Event\Config;

final readonly class EventsConfig
{
    /**
     * @param array<string, list<(callable(object): void)|string>> $listeners
     */
    public function __construct(
        public array $listeners,
    ) {}
}
