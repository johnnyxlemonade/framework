<?php

declare(strict_types=1);

namespace Lemonade\Framework\Event;

interface EventDispatcherInterface
{
    public function dispatch(object $event): object;
}
