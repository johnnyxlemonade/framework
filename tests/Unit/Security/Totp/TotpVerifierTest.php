<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Security\Totp;

use DateTimeImmutable;
use Lemonade\Framework\Clock\ClockInterface;
use Lemonade\Framework\Security\Totp\TotpAlgorithm;
use Lemonade\Framework\Security\Totp\TotpConfiguration;
use Lemonade\Framework\Security\Totp\TotpSecret;
use Lemonade\Framework\Security\Totp\TotpVerifier;
use Lemonade\Framework\Security\Totp\VerificationWindow;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class TotpVerifierTest extends TestCase
{
    #[DataProvider('rfc6238Vectors')]
    public function testVerifiesRfc6238Vectors(
        TotpAlgorithm $algorithm,
        string $secret,
        int $timestamp,
        string $code,
    ): void {
        $verification = $this->verifier($timestamp)->verify(
            TotpSecret::fromBase32($secret),
            $code,
            new TotpConfiguration($algorithm, 30, 8),
            new VerificationWindow(0, 0),
        );

        self::assertTrue($verification->isValid());
        self::assertSame(intdiv($timestamp, 30), $verification->matchedTimeStep());
    }

    public function testVerifiesSixDigitCodes(): void
    {
        $verification = $this->verifier(59)->verify(
            TotpSecret::fromBase32('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'),
            '287082',
            new TotpConfiguration(),
            new VerificationWindow(0, 0),
        );

        self::assertTrue($verification->isValid());
        self::assertSame(1, $verification->matchedTimeStep());
    }

    public function testReturnsThePastMatchedTimeStepInsideTheConfiguredWindow(): void
    {
        $verification = $this->verifier(60)->verify(
            TotpSecret::fromBase32('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'),
            '287082',
            new TotpConfiguration(),
            new VerificationWindow(1, 0),
        );

        self::assertTrue($verification->isValid());
        self::assertSame(1, $verification->matchedTimeStep());
    }

    public function testRejectsCodesOutsideTheConfiguredWindow(): void
    {
        $verification = $this->verifier(60)->verify(
            TotpSecret::fromBase32('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'),
            '287082',
            new TotpConfiguration(),
            new VerificationWindow(0, 0),
        );

        self::assertFalse($verification->isValid());
        self::assertNull($verification->matchedTimeStep());
    }

    public function testRejectsMalformedAndInvalidCodes(): void
    {
        $verifier = $this->verifier(59);
        $secret = TotpSecret::fromBase32('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ');

        foreach (['', '12345', '1234567', '12345a', '000000'] as $code) {
            $verification = $verifier->verify($secret, $code, new TotpConfiguration(), new VerificationWindow(0, 0));

            self::assertFalse($verification->isValid());
            self::assertNull($verification->matchedTimeStep());
        }
    }

    public function testChecksCurrentThenNearestPastAndFutureStepsInDeterministicOrder(): void
    {
        $method = new ReflectionMethod(TotpVerifier::class, 'candidateTimeSteps');
        $timeSteps = $method->invoke($this->verifier(0), 100, new VerificationWindow(2, 2));

        self::assertSame([100, 99, 101, 98, 102], $timeSteps);
    }

    /**
     * @return iterable<string, array{TotpAlgorithm, string, int, string}>
     */
    public static function rfc6238Vectors(): iterable
    {
        yield 'SHA-1 at 59 seconds' => [
            TotpAlgorithm::SHA1,
            'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ',
            59,
            '94287082',
        ];
        yield 'SHA-256 at 1111111109 seconds' => [
            TotpAlgorithm::SHA256,
            'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQGEZA',
            1111111109,
            '68084774',
        ];
        yield 'SHA-512 at 20000000000 seconds' => [
            TotpAlgorithm::SHA512,
            str_repeat('GEZDGNBVGY3TQOJQ', 6) . 'GEZDGNA',
            20000000000,
            '47863826',
        ];
    }

    private function verifier(int $timestamp): TotpVerifier
    {
        return new TotpVerifier(new TotpFixedClock($timestamp));
    }
}

final readonly class TotpFixedClock implements ClockInterface
{
    public function __construct(private int $timestamp)
    {
    }

    public function now(): DateTimeImmutable
    {
        return (new DateTimeImmutable())->setTimestamp($this->timestamp);
    }
}
