<?php

declare(strict_types=1);

namespace Lemonade\Framework\Clock;

use DateTimeImmutable;

interface ClockInterface
{
    public function now(): DateTimeImmutable;
}
