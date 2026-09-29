<?php

declare(strict_types=1);

namespace Lemonade\Framework\Event\Config;

use Lemonade\Framework\Event\EventListenerDefinition;

final readonly class EventsConfig
{
    /**
     * @param array<string, list<array{listener:string,method:string,priority:int}>> $listeners
     */
    public function __construct(
        public array $listeners,
        /** @var list<EventListenerDefinition> */
        public array $definitions = [],
    ) {
    }
}
