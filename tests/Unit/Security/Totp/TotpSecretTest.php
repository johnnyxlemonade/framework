<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Security\Totp;

use Lemonade\Framework\Security\Totp\Exception\InvalidTotpSecretException;
use Lemonade\Framework\Security\Totp\TotpSecret;
use PHPUnit\Framework\TestCase;

final class TotpSecretTest extends TestCase
{
    public function testAcceptsFormattedBase32AndExportsCanonicalForm(): void
    {
        $secret = TotpSecret::fromBase32('gezd gn-bvgy3tqojqgezdgnbvgy3tqojqaa======');

        self::assertSame('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQAA', $secret->toBase32());
    }

    public function testRejectsMalformedBase32(): void
    {
        $this->expectException(InvalidTotpSecretException::class);

        TotpSecret::fromBase32('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJ!');
    }

    public function testRejectsNonCanonicalTrailingBits(): void
    {
        $this->expectException(InvalidTotpSecretException::class);

        TotpSecret::fromBase32('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQAB');
    }

    public function testRejectsInvalidRfc4648Padding(): void
    {
        foreach ([
            'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ=',
            'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ======',
            'GEZDGNBVGY3TQOJQGEZD=GNBVGY3TQOJQ',
            'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQAA=====',
        ] as $value) {
            try {
                TotpSecret::fromBase32($value);
                self::fail(sprintf('Expected invalid Base32 padding for "%s".', $value));
            } catch (InvalidTotpSecretException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testRedactsSecretFromDebugAndRepresentationOutput(): void
    {
        $secret = TotpSecret::fromBase32('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ');
        $value = $secret->toBase32();

        $dump = $this->capturedOutput(static function () use ($secret): void {
            var_dump($secret);
        });
        $print = $this->capturedOutput(static function () use ($secret): void {
            print_r($secret);
        });

        self::assertStringContainsString('[redacted]', $dump);
        self::assertStringContainsString('[redacted]', $print);
        self::assertStringNotContainsString($value, $dump);
        self::assertStringNotContainsString($value, $print);
        self::assertStringNotContainsString($value, var_export($secret, true));
        self::assertSame('{}', json_encode($secret));
    }

    public function testRefusesSerialization(): void
    {
        $this->expectException(\LogicException::class);

        serialize(TotpSecret::fromBase32('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'));
    }

    public function testRejectsSecretsBelowTheMinimumStrength(): void
    {
        $this->expectException(InvalidTotpSecretException::class);

        TotpSecret::fromBase32('GEZDGNBVGY3TQOJQ');
    }

    /**
     * @param callable():void $operation
     */
    private function capturedOutput(callable $operation): string
    {
        ob_start();
        $operation();

        return (string) ob_get_clean();
    }
}
