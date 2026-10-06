<?php

declare(strict_types=1);

namespace Lemonade\Framework\Support;

use InvalidArgumentException;

/**
 * Converts configuration byte limits into positive integers without accepting lossy numeric formats.
 *
 * KB, MB, and GB always use binary multipliers of 1024, 1024 squared, and 1024 cubed respectively.
 */
final readonly class ByteSizeParser
{
    private const array MULTIPLIERS = [
        'B' => 1,
        'KB' => 1024,
        'MB' => 1024 * 1024,
        'GB' => 1024 * 1024 * 1024,
    ];

    /**
     * Parses raw positive bytes or a positive whole number with a case-insensitive B, KB, MB, or GB suffix.
     *
     * Whitespace before a suffix is allowed; decimals, zero, negative values, unsupported units, and integer
     * overflow are rejected instead of being rounded or clamped.
     *
     * @throws InvalidArgumentException When the value cannot represent a positive byte limit in the PHP integer range
     */
    public function parse(mixed $value): int
    {
        if (is_int($value)) {
            return $this->positiveInteger($value);
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException($this->invalidValueMessage());
        }

        $normalized = trim($value);

        if (preg_match('/^([1-9][0-9]*)\s*(B|KB|MB|GB)$/iD', $normalized, $matches) === 1) {
            return $this->withUnit($matches[1], strtoupper($matches[2]));
        }

        if (preg_match('/^[1-9][0-9]*$/D', $normalized) === 1) {
            return $this->decimalInteger($normalized);
        }

        throw new InvalidArgumentException($this->invalidValueMessage());
    }

    private function positiveInteger(int $value): int
    {
        if ($value <= 0) {
            throw new InvalidArgumentException($this->invalidValueMessage());
        }

        return $value;
    }

    private function withUnit(string $digits, string $unit): int
    {
        $value = $this->decimalInteger($digits);
        $multiplier = self::MULTIPLIERS[$unit];

        if ($value > intdiv(PHP_INT_MAX, $multiplier)) {
            throw new InvalidArgumentException('Byte size exceeds the supported integer range.');
        }

        return $value * $multiplier;
    }

    private function decimalInteger(string $digits): int
    {
        $maximum = (string) PHP_INT_MAX;

        if (
            strlen($digits) > strlen($maximum)
            || (strlen($digits) === strlen($maximum) && strcmp($digits, $maximum) > 0)
        ) {
            throw new InvalidArgumentException('Byte size exceeds the supported integer range.');
        }

        return (int) $digits;
    }

    private function invalidValueMessage(): string
    {
        return 'Byte size must be a positive whole-byte integer or a value such as "10MB".';
    }
}
