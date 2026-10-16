<?php

declare(strict_types=1);

namespace Lemonade\Framework\Clock;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Reads wall-clock time in an explicitly configured timezone or PHP's current default timezone.
 */
final readonly class SystemClock implements ClockInterface
{
    /**
     * Selects the timezone applied to generated timestamps while leaving the PHP default untouched.
     */
    public function __construct(
        private ?DateTimeZone $timezone = null,
    ) {
    }

    /**
     * Returns a new immutable timestamp for the current system time.
     */
    public function now(): DateTimeImmutable
    {
        if ($this->timezone instanceof DateTimeZone) {
            return new DateTimeImmutable('now', $this->timezone);
        }

        return new DateTimeImmutable('now');
    }
}
