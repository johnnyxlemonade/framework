<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Security\Totp;

use Lemonade\Framework\Security\Totp\Exception\TotpSecretGenerationException;
use Lemonade\Framework\Security\Totp\TotpSecretGenerator;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class TotpSecretGeneratorTest extends TestCase
{
    public function testGeneratesTheMinimum160BitCanonicalBase32Secret(): void
    {
        self::assertFalse(function_exists('Lemonade\\Framework\\Security\\Totp\\random_bytes'));

        $secret = (new TotpSecretGenerator())->generate();

        self::assertSame(32, strlen($secret->toBase32()));
        self::assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret->toBase32());
    }

    #[RunInSeparateProcess]
    public function testWrapsRandomBytesFailureWithoutUsingAFallback(): void
    {
        require_once __DIR__ . '/Support/RandomBytesFailureStub.php';

        $this->expectException(TotpSecretGenerationException::class);
        $this->expectExceptionMessage('Unable to generate a cryptographically secure TOTP secret.');

        (new TotpSecretGenerator())->generate();
    }
}
