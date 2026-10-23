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
    public function testGeneratedSaltRemainsUnchangedWhenEmbeddedInBcrypt(): void
    {
        $salt = BaseCrypt::getSalt();
        $hash = crypt('salt check', '$2y$04$' . $salt);

        self::assertSame(22, strlen($salt));
        self::assertSame($salt, substr($hash, 7, 22));
    }

    public function testShortPasswordRetainsNativeBcryptRoundTrip(): void
    {
        $password = 'existing short password';
        $hash = BaseCrypt::encPassword($password, 4);

        self::assertSame(60, strlen($hash));
        self::assertTrue(password_verify($password, $hash));
        self::assertTrue(BaseCrypt::checkHash($hash, $password));
        self::assertFalse(BaseCrypt::checkHash($hash, $password . '!'));
    }

    public function testLongPasswordUsesEveryByteBeyondTheBcryptBoundary(): void
    {
        $prefix = str_repeat('a', 72);
        $password = $prefix . '-first-long-password-tail';
        $hash = BaseCrypt::encPassword($password, 4);

        self::assertSame(60, strlen($hash));
        self::assertTrue(BaseCrypt::checkHash($hash, $password));
        self::assertFalse(BaseCrypt::checkHash($hash, $prefix . '-different-password-tail'));
        self::assertFalse(password_verify($password, $hash));
    }

    public function testUnicodePasswordOver72BytesRoundTripsWithoutCharacterLoss(): void
    {
        $password = str_repeat('bezpečné heslo 🔐 ', 8);
        self::assertGreaterThan(72, strlen($password));

        $hash = BaseCrypt::encPassword($password, 4);

        self::assertTrue(BaseCrypt::checkHash($hash, $password));
        self::assertFalse(BaseCrypt::checkHash($hash, $password . 'x'));
    }

    public function testNativeBcryptIgnoresPasswordBytesAfter72(): void
    {
        $prefix = str_repeat('a', 72);
        $hash = password_hash($prefix, PASSWORD_BCRYPT, ['cost' => 4]);

        self::assertTrue(password_verify($prefix . '-first-tail', $hash));
        self::assertTrue(password_verify($prefix . '-different-tail', $hash));
    }

    public function testExistingNativeBcryptHashesRemainCompatible(): void
    {
        $password = 'legacy password value';
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

        self::assertTrue(BaseCrypt::checkHash($hash, $password));
        self::assertFalse(BaseCrypt::checkHash($hash, 'wrong password'));
        self::assertFalse(BaseCrypt::needsRehash($hash));
    }

    public function testExistingLongBcryptHashRemainsVerifiableDuringMigration(): void
    {
        $prefix = str_repeat('a', 72);
        $legacyHash = password_hash($prefix, PASSWORD_BCRYPT, ['cost' => 4]);

        self::assertTrue(BaseCrypt::checkHash($legacyHash, $prefix . '-historical-tail'));
    }

    public function testLegacy2aHashRemainsVerifiableAndRequestsRehash(): void
    {
        $password = 'legacy 2a password';
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]);
        $legacyHash = '$2a$' . substr($hash, 4);

        self::assertTrue(BaseCrypt::checkHash($legacyHash, $password));
        self::assertFalse(BaseCrypt::checkHash($legacyHash, 'wrong password'));
        self::assertTrue(BaseCrypt::needsRehash($legacyHash));
    }

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
