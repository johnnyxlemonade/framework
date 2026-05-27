<?php

declare(strict_types=1);

if (!function_exists('is_valid_ean')) {
    function is_valid_ean(?string $barcode): bool
    {
        if ($barcode === null || !ctype_digit($barcode)) {
            return false;
        }

        $validLengths = [8, 12, 13, 14, 17, 18];
        if (!in_array(strlen($barcode), $validLengths, true)) {
            return false;
        }

        $checkDigit = (int) substr($barcode, -1);
        $body = substr($barcode, 0, -1);

        $sum = 0;
        $even = false;

        for ($i = strlen($body) - 1; $i >= 0; $i--) {
            $digit = (int) $body[$i];
            $sum += $even ? $digit * 3 : $digit;
            $even = !$even;
        }

        $calculated = (10 - ($sum % 10)) % 10;

        return $checkDigit === $calculated;
    }
}
