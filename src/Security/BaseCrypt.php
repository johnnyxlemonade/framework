<?php

declare(strict_types=1);

namespace Lemonade\Framework\Security;

use Random\RandomException;

/**
 * Provides password hashing compatibility and cryptographically secure random password generation.
 */
final class BaseCrypt
{
    private const string ALGO = PASSWORD_BCRYPT;
    private const int COST = 10;

    /**
     * Returns a random 22-character fragment compatible with bcrypt salt encoding.
     *
     * @throws RandomException When the operating system cannot provide secure random bytes.
     */
    public static function getSalt(): string
    {
        return substr(strtr(base64_encode(random_bytes(16)), '+', '.'), 0, 22);
    }

    /**
     * Returns a lowercase hexadecimal password of the requested length, normalized to at least one character.
     *
     * The operation fails closed instead of substituting a weaker source when secure randomness is unavailable.
     *
     * @throws RandomException When the operating system cannot provide secure random bytes.
     */
    public static function generateRandomPassword(int $length = 16): string
    {
        if ($length < 1) {
            $length = 1;
        }

        $bytesLength = max(1, (int) ceil($length / 2));

        return substr(bin2hex(random_bytes($bytesLength)), 0, $length);
    }

    /**
     * Hashes a password with bcrypt using the supplied cost or the framework compatibility default.
     *
     * @throws \ValueError When the selected bcrypt cost is outside the supported range.
     */
    public static function encPassword(string $password, ?int $cost = null): string
    {
        return password_hash($password, self::ALGO, [
            'cost' => $cost ?? self::COST,
        ]);
    }

    /**
     * Verifies a password against bcrypt hashes, including legacy `$2a$` values.
     */
    public static function checkHash(string $hash, string $password): bool
    {
        if (str_starts_with($hash, '$2a$')) {
            return $hash === crypt($password, $hash);
        }

        return password_verify($password, $hash);
    }

    /**
     * Reports whether a hash must be upgraded to the current bcrypt algorithm and cost.
     */
    public static function needsRehash(string $hash): bool
    {
        if (str_starts_with($hash, '$2a$')) {
            return true;
        }

        return password_needs_rehash($hash, self::ALGO, [
            'cost' => self::COST,
        ]);
    }
}
