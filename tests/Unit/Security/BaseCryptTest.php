<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Security;

use Lemonade\Framework\Security\BaseCrypt;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Random\RandomException;

final class BaseCryptTest extends TestCase
{
    #[DataProvider('passwordLengths')]
    public function testGeneratesLowercaseHexPasswordWithRequestedLength(int $length): void
    {
        $password = BaseCrypt::generateRandomPassword($length);

        self::assertSame($length, strlen($password));
        self::assertMatchesRegularExpression('/^[a-f0-9]+$/', $password);
    }

    public function testNormalizesNonPositiveLengthToOneCharacter(): void
    {
        $password = BaseCrypt::generateRandomPassword(0);

        self::assertSame(1, strlen($password));
        self::assertMatchesRegularExpression('/^[a-f0-9]$/', $password);
    }

    public function testGlobalHelperPreservesThePasswordFormat(): void
    {
        $password = basecrypt_password(17);

        self::assertSame(17, strlen($password));
        self::assertMatchesRegularExpression('/^[a-f0-9]{17}$/', $password);
    }

    #[RunInSeparateProcess]
    public function testPropagatesRandomBytesFailureWithoutUsingFallback(): void
    {
        require_once __DIR__ . '/Support/RandomBytesFailureStub.php';

        $this->expectException(RandomException::class);
        $this->expectExceptionMessage('random_bytes failed for 8 bytes.');

        BaseCrypt::generateRandomPassword(16);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function passwordLengths(): iterable
    {
        yield 'single character' => [1];
        yield 'odd length' => [15];
        yield 'default length' => [16];
        yield 'longer odd length' => [31];
        yield 'longer even length' => [32];
    }
}
