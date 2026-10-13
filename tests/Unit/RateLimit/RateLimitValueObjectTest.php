<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\RateLimit;

use Lemonade\Framework\RateLimit\RateLimitPolicy;
use Lemonade\Framework\RateLimit\RateLimitResult;
use PHPUnit\Framework\TestCase;

final class RateLimitValueObjectTest extends TestCase
{
    public function testPolicyExposesItsValidatedValues(): void
    {
        $policy = new RateLimitPolicy(5, 900);

        self::assertSame(5, $policy->attempts());
        self::assertSame(900, $policy->windowSeconds());
    }

    public function testPolicyRejectsNonPositiveValues(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new RateLimitPolicy(0, 900);
    }

    public function testResultExposesItsImmutableDecision(): void
    {
        $result = new RateLimitResult(false, 0, 42);

        self::assertFalse($result->allowed());
        self::assertSame(0, $result->remaining());
        self::assertSame(42, $result->retryAfterSeconds());
    }
}
