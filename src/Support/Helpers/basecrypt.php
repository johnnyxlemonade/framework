<?php

declare(strict_types=1);

use Lemonade\Framework\Security\BaseCrypt;

if (!function_exists('basecrypt_hash')) {
    function basecrypt_hash(string $password, ?int $cost = null): string
    {
        return BaseCrypt::encPassword($password, $cost);
    }
}

if (!function_exists('basecrypt_check')) {
    function basecrypt_check(string $hash, string $password): bool
    {
        return BaseCrypt::checkHash($hash, $password);
    }
}

if (!function_exists('basecrypt_needs_rehash')) {
    function basecrypt_needs_rehash(string $hash): bool
    {
        return BaseCrypt::needsRehash($hash);
    }
}

if (!function_exists('basecrypt_password')) {
    function basecrypt_password(int $length = 16): string
    {
        return BaseCrypt::generateRandomPassword($length);
    }
}

if (!function_exists('basecrypt_salt')) {
    function basecrypt_salt(): string
    {
        return BaseCrypt::getSalt();
    }
}
