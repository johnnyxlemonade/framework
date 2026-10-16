<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Session;

use InvalidArgumentException;
use Lemonade\Framework\Session\SessionGarbageCollectionPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SessionGarbageCollectionPolicyTest extends TestCase
{
    public function testItProvidesCanonicalDefaults(): void
    {
        $policy = new SessionGarbageCollectionPolicy();

        self::assertSame(2, SessionGarbageCollectionPolicy::DEFAULT_PROBABILITY);
        self::assertSame(100, SessionGarbageCollectionPolicy::DEFAULT_DIVISOR);
        self::assertSame(2, $policy->probability());
        self::assertSame(100, $policy->divisor());
    }

    public function testCanonicalPolicyProvidesBoundedCleanupCapacityReserve(): void
    {
        $expectedProcessedEntriesPerRequest = SessionGarbageCollectionPolicy::DEFAULT_PROBABILITY
            * 100
            / SessionGarbageCollectionPolicy::DEFAULT_DIVISOR;

        self::assertGreaterThan(1, $expectedProcessedEntriesPerRequest);
    }

    public function testExtremePoliciesMakeDeterministicDecisions(): void
    {
        $disabled = new SessionGarbageCollectionPolicy(0, 100);
        $always = new SessionGarbageCollectionPolicy(1, 1);

        self::assertFalse($disabled->shouldRun());
        self::assertTrue($always->shouldRun());
    }

    #[DataProvider('invalidPolicies')]
    public function testItRejectsInvalidProbabilityRanges(int $probability, int $divisor): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Session GC probability must be between zero and its positive divisor.',
        );

        new SessionGarbageCollectionPolicy($probability, $divisor);
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function invalidPolicies(): iterable
    {
        yield 'negative probability' => [-1, 100];
        yield 'zero divisor' => [0, 0];
        yield 'probability exceeds divisor' => [101, 100];
    }
}
