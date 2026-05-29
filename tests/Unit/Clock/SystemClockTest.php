<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Clock;

use DateTimeZone;
use Lemonade\Framework\Clock\SystemClock;
use PHPUnit\Framework\TestCase;

final class SystemClockTest extends TestCase
{
    public function testNowUsesInjectedTimezone(): void
    {
        $clock = new SystemClock(new DateTimeZone('UTC'));
        $now = $clock->now();

        self::assertSame('UTC', $now->getTimezone()->getName());
    }
}

