<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Security\Totp;

use Lemonade\Framework\Security\Totp\Exception\InvalidTotpConfigurationException;
use Lemonade\Framework\Security\Totp\TotpAlgorithm;
use Lemonade\Framework\Security\Totp\TotpConfiguration;
use PHPUnit\Framework\TestCase;

final class TotpConfigurationTest extends TestCase
{
    public function testUsesTheInteroperableDefaultConfiguration(): void
    {
        $configuration = new TotpConfiguration();

        self::assertSame(TotpAlgorithm::SHA1, $configuration->algorithm);
        self::assertSame(30, $configuration->periodSeconds);
        self::assertSame(6, $configuration->digits);
    }

    public function testAllowsEveryPositivePeriodWithoutImposingApplicationPolicy(): void
    {
        self::assertSame(1, (new TotpConfiguration(periodSeconds: 1))->periodSeconds);
        self::assertSame(300, (new TotpConfiguration(periodSeconds: 300))->periodSeconds);
    }

    public function testRejectsNonPositivePeriods(): void
    {
        $this->expectException(InvalidTotpConfigurationException::class);

        new TotpConfiguration(periodSeconds: 0);
    }

    public function testRejectsUnsupportedDigits(): void
    {
        $this->expectException(InvalidTotpConfigurationException::class);

        new TotpConfiguration(digits: 7);
    }
}
