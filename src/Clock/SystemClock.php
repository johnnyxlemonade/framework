<?php

declare(strict_types=1);

namespace Lemonade\Framework\Clock;

use DateTimeImmutable;
use DateTimeZone;

final class SystemClock implements ClockInterface
{
    public function __construct(
        private readonly ?DateTimeZone $timezone = null,
    ) {}

    public function now(): DateTimeImmutable
    {
        if ($this->timezone instanceof DateTimeZone) {
            return new DateTimeImmutable('now', $this->timezone);
        }

        return new DateTimeImmutable('now');
    }
}
