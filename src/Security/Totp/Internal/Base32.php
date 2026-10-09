<?php

declare(strict_types=1);

namespace Lemonade\Framework\Security\Totp\Internal;

use Lemonade\Framework\Security\Totp\Exception\InvalidTotpSecretException;

/**
 * Encodes canonical unpadded Base32 and accepts human-entered Base32 formatting on input.
 *
 * @internal
 */
final class Base32
{
    private const string ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Produces the unpadded canonical Base32 form for arbitrary binary bytes.
     */
    public function encode(string $bytes): string
    {
        if ($bytes === '') {
            return '';
        }

        $buffer = 0;
        $bits = 0;
        $encoded = '';

        foreach (str_split($bytes) as $byte) {
            $buffer = ($buffer << 8) | ord($byte);
            $bits += 8;

            while ($bits >= 5) {
                $bits -= 5;
                $encoded .= self::ALPHABET[($buffer >> $bits) & 0x1F];
            }

            $buffer = $bits === 0 ? 0 : $buffer & ((1 << $bits) - 1);
        }

        if ($bits > 0) {
            $encoded .= self::ALPHABET[($buffer << (5 - $bits)) & 0x1F];
        }

        return $encoded;
    }

    /**
     * Decodes a formatted Base32 value and rejects non-canonical trailing bits.
     *
     * @throws InvalidTotpSecretException
     */
    public function decode(string $value): string
    {
        $normalized = $this->normalize($value);
        $length = strlen($normalized);
        $remainder = $length % 8;

        if (in_array($remainder, [1, 3, 6], true)) {
            throw new InvalidTotpSecretException('TOTP secret must be a valid Base32 value.');
        }

        $buffer = 0;
        $bits = 0;
        $decoded = '';

        foreach (str_split($normalized) as $character) {
            $index = strpos(self::ALPHABET, $character);
            if ($index === false) {
                throw new InvalidTotpSecretException('TOTP secret must be a valid Base32 value.');
            }

            $buffer = ($buffer << 5) | $index;
            $bits += 5;

            while ($bits >= 8) {
                $bits -= 8;
                $decoded .= chr(($buffer >> $bits) & 0xFF);
            }

            $buffer = $bits === 0 ? 0 : $buffer & ((1 << $bits) - 1);
        }

        if ($bits > 0 && ($buffer & ((1 << $bits) - 1)) !== 0) {
            throw new InvalidTotpSecretException('TOTP secret must be a valid Base32 value.');
        }

        return $decoded;
    }

    /**
     * Removes accepted presentation separators and padding before validating the Base32 alphabet.
     *
     * @throws InvalidTotpSecretException
     */
    private function normalize(string $value): string
    {
        $value = strtoupper(str_replace([' ', '-'], '', trim($value)));
        $unPadded = rtrim($value, '=');
        $paddingLength = strlen($value) - strlen($unPadded);

        if ($unPadded === '' || $unPadded . str_repeat('=', strlen($value) - strlen($unPadded)) !== $value) {
            throw new InvalidTotpSecretException('TOTP secret must be a valid Base32 value.');
        }

        if (preg_match('/[^A-Z2-7]/', $unPadded) === 1) {
            throw new InvalidTotpSecretException('TOTP secret must be a valid Base32 value.');
        }

        if ($paddingLength > 0 && $paddingLength !== $this->expectedPaddingLength(strlen($unPadded))) {
            throw new InvalidTotpSecretException('TOTP secret must use valid RFC 4648 Base32 padding.');
        }

        return $unPadded;
    }

    /**
     * Returns the exact RFC 4648 padding count for a valid unpadded Base32 length.
     */
    private function expectedPaddingLength(int $length): int
    {
        return match ($length % 8) {
            0 => 0,
            2 => 6,
            4 => 4,
            5 => 3,
            7 => 1,
            default => throw new InvalidTotpSecretException('TOTP secret must be a valid Base32 value.'),
        };
    }
}
