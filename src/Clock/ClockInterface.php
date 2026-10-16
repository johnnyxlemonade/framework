<?php

declare(strict_types=1);

namespace Lemonade\Framework\Clock;

use DateTimeImmutable;

/**
 * Provides current time through an injectable boundary without exposing its time source.
 */
interface ClockInterface
{
    /**
     * Returns an immutable timestamp representing the clock's current instant.
     */
    public function now(): DateTimeImmutable;
}
