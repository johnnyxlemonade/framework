<?php

declare(strict_types=1);

namespace Lemonade\Framework\Security;

final class BaseCrypt
{
    private const ALGO = PASSWORD_BCRYPT;
    private const COST = 10;

    public static function getSalt(): string
    {
        return substr(strtr(base64_encode(random_bytes(16)), '+', '.'), 0, 22);
    }

    public static function generateRandomPassword(int $length = 16): string
    {
        if ($length < 1) {
            $length = 1;
        }

        try {
            $bytesLength = max(1, (int) ceil($length / 2));

            return substr(bin2hex(random_bytes($bytesLength)), 0, $length);
        } catch (\Throwable) {
            return self::fallbackRandomString($length);
        }
    }

    public static function encPassword(string $password, ?int $cost = null): string
    {
        return password_hash($password, self::ALGO, [
            'cost' => $cost ?? self::COST,
        ]);
    }

    public static function checkHash(string $hash, string $password): bool
    {
        if (str_starts_with($hash, '$2a$')) {
            return $hash === crypt($password, $hash);
        }

        return password_verify($password, $hash);
    }

    public static function needsRehash(string $hash): bool
    {
        if (str_starts_with($hash, '$2a$')) {
            return true;
        }

        return password_needs_rehash($hash, self::ALGO, [
            'cost' => self::COST,
        ]);
    }

    private static function fallbackRandomString(int $length): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefhjkmnprstuvwxyz23456789';
        $charsLength = strlen($chars);
        $result = '';
        $seed = hash('sha256', microtime(true) . uniqid('', true));

        for ($i = 0; $i < $length; $i++) {
            $pair = substr($seed, ($i * 2) % strlen($seed), 2);
            $index = hexdec($pair) % $charsLength;
            $result .= $chars[$index];
        }

        return $result;
    }
}
