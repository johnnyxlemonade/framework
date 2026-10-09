<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Property\Security;

use DateTimeImmutable;
use Eris\Generators;
use Eris\TestTrait;
use Lemonade\Framework\Clock\ClockInterface;
use Lemonade\Framework\Security\Totp\Exception\InvalidTotpConfigurationException;
use Lemonade\Framework\Security\Totp\Exception\InvalidTotpSecretException;
use Lemonade\Framework\Security\Totp\Internal\TotpCodeGenerator;
use Lemonade\Framework\Security\Totp\TotpAlgorithm;
use Lemonade\Framework\Security\Totp\TotpConfiguration;
use Lemonade\Framework\Security\Totp\TotpProvisioningUriFactory;
use Lemonade\Framework\Security\Totp\TotpSecret;
use Lemonade\Framework\Security\Totp\TotpVerifier;
use Lemonade\Framework\Security\Totp\VerificationWindow;
use PHPUnit\Framework\TestCase;
use Throwable;

final class TotpPropertiesTest extends TestCase
{
    use TestTrait;

    private const int PROPERTY_CASES = 500;

    public function testCanonicalBase32RoundTripsAndNormalizesSupportedVariants(): void
    {
        $this
            ->limitTo(self::PROPERTY_CASES)
            ->forAll(self::canonicalBase32Generator())
            ->then(static function (string $canonical): void {
                $secret = TotpSecret::fromBase32($canonical);
                $paddedCanonical = $canonical . 'AA';
                $variants = [
                    strtolower($canonical),
                    self::separated(strtolower($canonical)),
                    $paddedCanonical . '======',
                ];

                self::assertSame($canonical, $secret->toBase32());
                self::assertSame($canonical, TotpSecret::fromBase32($secret->toBase32())->toBase32());

                foreach ($variants as $variant) {
                    $normalized = TotpSecret::fromBase32($variant)->toBase32();

                    self::assertSame(
                        $variant === $paddedCanonical . '======' ? $paddedCanonical : $canonical,
                        $normalized,
                    );
                    self::assertSame($normalized, TotpSecret::fromBase32($normalized)->toBase32());
                }
            });
    }

    public function testInvalidBase32ClassesAreAlwaysRejected(): void
    {
        $this
            ->limitTo(self::PROPERTY_CASES)
            ->forAll(self::canonicalBase32Generator())
            ->then(static function (string $canonical): void {
                $invalidValues = [
                    $canonical . '!',
                    substr($canonical, 0, 16) . '=' . substr($canonical, 16),
                    $canonical . '=',
                    $canonical . '======',
                    $canonical . 'AA=====',
                    $canonical . 'AB',
                ];

                foreach ($invalidValues as $value) {
                    $rejected = false;

                    try {
                        TotpSecret::fromBase32($value);
                    } catch (InvalidTotpSecretException) {
                        $rejected = true;
                    }

                    self::assertTrue($rejected, sprintf('Expected invalid Base32 input "%s".', $value));
                }
            });
    }

    public function testTotpGenerationIsDeterministicAndFixedWidth(): void
    {
        $generator = new TotpCodeGenerator();

        $this
            ->limitTo(self::PROPERTY_CASES)
            ->forAll(
                self::canonicalBase32Generator(),
                Generators::choose(0, 2000000000),
                Generators::elements([TotpAlgorithm::SHA1, TotpAlgorithm::SHA256, TotpAlgorithm::SHA512]),
                Generators::elements([6, 8]),
                Generators::choose(1, 300),
            )
            ->then(static function (
                string $canonical,
                int $timestamp,
                TotpAlgorithm $algorithm,
                int $digits,
                int $period,
            ) use ($generator): void {
                $secret = TotpSecret::fromBase32($canonical);
                $configuration = new TotpConfiguration($algorithm, $period, $digits);
                $first = $generator->generate($secret, $timestamp, $configuration);
                $second = $generator->generate($secret, $timestamp, $configuration);

                self::assertSame($first, $second);
                self::assertSame($digits, strlen($first));
                self::assertMatchesRegularExpression('/^[0-9]{' . $digits . '}$/D', $first);
            });
    }

    public function testVerificationWindowAcceptsExactlyItsCandidateSteps(): void
    {
        $generator = new TotpCodeGenerator();

        $this
            ->limitTo(self::PROPERTY_CASES)
            ->forAll(
                self::canonicalBase32Generator(),
                Generators::elements([TotpAlgorithm::SHA1, TotpAlgorithm::SHA256, TotpAlgorithm::SHA512]),
                Generators::elements([6, 8]),
                Generators::choose(1, 300),
                Generators::choose(0, 2),
                Generators::choose(0, 2),
                Generators::choose(5, 500000),
            )
            ->then(static function (
                string $canonical,
                TotpAlgorithm $algorithm,
                int $digits,
                int $period,
                int $pastSteps,
                int $futureSteps,
                int $currentStep,
            ) use ($generator): void {
                $secret = TotpSecret::fromBase32($canonical);
                $configuration = new TotpConfiguration($algorithm, $period, $digits);
                $window = new VerificationWindow($pastSteps, $futureSteps);
                $timestamp = $currentStep * $period;
                $verifier = new TotpVerifier(new TotpPropertyClock($timestamp));
                $candidateSteps = self::candidateSteps($currentStep, $window);
                $codes = [];

                foreach ($candidateSteps as $step) {
                    $codes[$step] = $generator->generate($secret, $step * $period, $configuration);
                }

                foreach ($candidateSteps as $step) {
                    $verification = $verifier->verify($secret, $codes[$step], $configuration, $window);

                    self::assertTrue($verification->isValid());
                    self::assertSame(
                        self::firstMatchingStep($candidateSteps, $codes, $codes[$step]),
                        $verification->matchedTimeStep(),
                    );
                }

                foreach ([$currentStep - $pastSteps - 1, $currentStep + $futureSteps + 1] as $outsideStep) {
                    $outsideCode = $generator->generate($secret, $outsideStep * $period, $configuration);

                    if (!in_array($outsideCode, $codes, true)) {
                        self::assertFalse($verifier->verify($secret, $outsideCode, $configuration, $window)->isValid());
                    }
                }
            });
    }

    public function testConfigurationAcceptsOnlyContractValues(): void
    {
        $this
            ->limitTo(self::PROPERTY_CASES)
            ->forAll(
                Generators::choose(1, 10000),
                Generators::elements([6, 8]),
                Generators::choose(0, 2),
                Generators::choose(0, 2),
            )
            ->then(static function (int $period, int $digits, int $pastSteps, int $futureSteps): void {
                $configuration = new TotpConfiguration(periodSeconds: $period, digits: $digits);
                $window = new VerificationWindow($pastSteps, $futureSteps);

                self::assertSame($period, $configuration->periodSeconds);
                self::assertSame($digits, $configuration->digits);
                self::assertSame($pastSteps, $window->pastSteps);
                self::assertSame($futureSteps, $window->futureSteps);
            });
    }

    public function testConfigurationRejectsValuesOutsideTheContract(): void
    {
        $this
            ->limitTo(self::PROPERTY_CASES)
            ->forAll(
                Generators::choose(-100, 0),
                Generators::elements([-5, -1, 0, 1, 5, 7, 9, 12]),
                Generators::elements([-5, -1, 3, 4, 10]),
            )
            ->then(static function (int $invalidPeriod, int $invalidDigits, int $invalidSteps): void {
                self::assertThrows(
                    static fn(): TotpConfiguration => new TotpConfiguration(periodSeconds: $invalidPeriod),
                    InvalidTotpConfigurationException::class,
                );
                self::assertThrows(
                    static fn(): TotpConfiguration => new TotpConfiguration(digits: $invalidDigits),
                    InvalidTotpConfigurationException::class,
                );
                self::assertThrows(
                    static fn(): VerificationWindow => new VerificationWindow($invalidSteps, 0),
                    InvalidTotpConfigurationException::class,
                );
                self::assertThrows(
                    static fn(): VerificationWindow => new VerificationWindow(0, $invalidSteps),
                    InvalidTotpConfigurationException::class,
                );
            });
    }

    public function testProvisioningUriPreservesValuesWithoutQueryInjection(): void
    {
        $factory = new TotpProvisioningUriFactory();

        $this
            ->limitTo(self::PROPERTY_CASES)
            ->forAll(
                self::canonicalBase32Generator(),
                Generators::elements(self::provisioningValues()),
                Generators::elements(self::provisioningValues()),
                Generators::elements([TotpAlgorithm::SHA1, TotpAlgorithm::SHA256, TotpAlgorithm::SHA512]),
                Generators::elements([6, 8]),
                Generators::choose(1, 300),
            )
            ->then(static function (
                string $canonical,
                string $issuer,
                string $label,
                TotpAlgorithm $algorithm,
                int $digits,
                int $period,
            ) use ($factory): void {
                $configuration = new TotpConfiguration($algorithm, $period, $digits);
                $uri = $factory->create($issuer, $label, TotpSecret::fromBase32($canonical), $configuration);
                $parts = parse_url($uri);

                self::assertIsArray($parts);
                self::assertSame('otpauth', $parts['scheme'] ?? null);
                self::assertSame('totp', $parts['host'] ?? null);
                self::assertSame($issuer . ':' . $label, rawurldecode(ltrim((string) ($parts['path'] ?? ''), '/')));

                parse_str((string) ($parts['query'] ?? ''), $query);

                self::assertSame([
                    'secret' => $canonical,
                    'issuer' => $issuer,
                    'algorithm' => match ($algorithm) {
                        TotpAlgorithm::SHA1 => 'SHA1',
                        TotpAlgorithm::SHA256 => 'SHA256',
                        TotpAlgorithm::SHA512 => 'SHA512',
                    },
                    'digits' => (string) $digits,
                    'period' => (string) $period,
                ], $query);
            });
    }

    public function testSecretLifecycleDoesNotExposeSecretMaterial(): void
    {
        $generator = new TotpCodeGenerator();
        $factory = new TotpProvisioningUriFactory();

        $this
            ->limitTo(self::PROPERTY_CASES)
            ->forAll(self::canonicalBase32Generator())
            ->then(static function (string $canonical) use ($generator, $factory): void {
                $secret = self::passSecret(TotpSecret::fromBase32($canonical));
                $configuration = new TotpConfiguration();
                $timestamp = 123456;
                $code = $generator->generate($secret, $timestamp, $configuration);
                $verification = (new TotpVerifier(new TotpPropertyClock($timestamp)))->verify(
                    $secret,
                    $code,
                    $configuration,
                    new VerificationWindow(0, 0),
                );

                self::assertSame($canonical, $secret->toBase32());
                self::assertTrue($verification->isValid());
                self::assertStringContainsString('secret=' . $canonical, $factory->create('issuer', 'account', $secret, $configuration));

                foreach ([
                    self::capturedOutput(static function () use ($secret): void {
                        var_dump($secret);
                    }),
                    self::capturedOutput(static function () use ($secret): void {
                        print_r($secret);
                    }),
                    var_export($secret, true),
                    (string) json_encode($secret),
                ] as $representation) {
                    self::assertStringNotContainsString($canonical, $representation);
                }

                $serializationRejected = false;

                try {
                    serialize($secret);
                } catch (\LogicException) {
                    $serializationRejected = true;
                }

                self::assertTrue($serializationRejected, 'TOTP secret serialization must be rejected.');

                $cloneRejected = false;

                try {
                    self::assertNotSame($secret, clone $secret);
                } catch (Throwable) {
                    $cloneRejected = true;
                }

                self::assertTrue($cloneRejected, 'TOTP secret cloning must be rejected.');
            });
    }

    /**
     * @return \Eris\Generator<string>
     */
    private static function canonicalBase32Generator(): \Eris\Generator
    {
        return Generators::map(
            static fn(array $characters): string => implode('', $characters),
            Generators::vector(32, Generators::elements(str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'))),
        );
    }

    private static function separated(string $value): string
    {
        return implode(' - ', str_split($value, 4));
    }

    /**
     * @return list<int>
     */
    private static function candidateSteps(int $currentStep, VerificationWindow $window): array
    {
        $steps = [$currentStep];
        $maximumDistance = max($window->pastSteps, $window->futureSteps);

        for ($distance = 1; $distance <= $maximumDistance; $distance++) {
            if ($distance <= $window->pastSteps) {
                $steps[] = $currentStep - $distance;
            }

            if ($distance <= $window->futureSteps) {
                $steps[] = $currentStep + $distance;
            }
        }

        return $steps;
    }

    /**
     * @param list<int> $steps
     * @param array<int, string> $codes
     */
    private static function firstMatchingStep(array $steps, array $codes, string $code): int
    {
        foreach ($steps as $step) {
            if ($codes[$step] === $code) {
                return $step;
            }
        }

        throw new \LogicException('A generated candidate code must have a matching step.');
    }

    /**
     * @return list<string>
     */
    private static function provisioningValues(): array
    {
        return [
            'Acme',
            'Český účet',
            'alice+admin@example.test',
            'A&B=1',
            'name?next=/admin',
            'hash#fragment',
            'percent%25',
            'colon:value',
            'slash/value',
            '漢字 😀',
        ];
    }

    private static function passSecret(TotpSecret $secret): TotpSecret
    {
        return $secret;
    }

    /**
     * @param callable():void $operation
     */
    private static function capturedOutput(callable $operation): string
    {
        ob_start();
        $operation();

        return (string) ob_get_clean();
    }

    /**
     * @param callable():object $operation
     * @param class-string<Throwable> $exceptionClass
     */
    private static function assertThrows(callable $operation, string $exceptionClass): void
    {
        try {
            $operation();
        } catch (Throwable $exception) {
            self::assertInstanceOf($exceptionClass, $exception);

            return;
        }

        self::fail(sprintf('Expected %s.', $exceptionClass));
    }
}

final readonly class TotpPropertyClock implements ClockInterface
{
    public function __construct(private int $timestamp)
    {
    }

    public function now(): DateTimeImmutable
    {
        return (new DateTimeImmutable())->setTimestamp($this->timestamp);
    }
}
