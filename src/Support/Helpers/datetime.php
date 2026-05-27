<?php

declare(strict_types=1);

if (!function_exists('is_valid_date')) {
    function is_valid_date(?string $date, string $format = 'Y-m-d H:i:s'): bool
    {
        if ($date === null || $date === '') {
            return false;
        }

        $parsed = DateTime::createFromFormat($format, $date);

        return $parsed !== false && $parsed->format($format) === $date;
    }
}

if (!function_exists('to_date_immutable')) {
    function to_date_immutable(?string $date): ?DateTimeImmutable
    {
        if ($date === null || $date === '' || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
            return null;
        }

        try {
            return new DateTimeImmutable($date);
        } catch (Exception) {
            return null;
        }
    }
}

if (!function_exists('from_date_immutable')) {
    function from_date_immutable(?DateTimeImmutable $date, string $format = 'Y-m-d H:i:s'): ?string
    {
        return $date?->format($format);
    }
}

if (!function_exists('days_between')) {
    function days_between(mixed $dt1, mixed $dt2): DateInterval|false
    {
        $rawDate1 = $dt1 instanceof DateTimeInterface || is_scalar($dt1) || $dt1 instanceof Stringable ? $dt1 : null;
        $rawDate2 = $dt2 instanceof DateTimeInterface || is_scalar($dt2) || $dt2 instanceof Stringable ? $dt2 : null;
        if ($rawDate1 === null || $rawDate2 === null) {
            return false;
        }

        try {
            $date1 = ($rawDate1 instanceof DateTimeInterface) ? $rawDate1 : new DateTime((string) $rawDate1);
            $date2 = ($rawDate2 instanceof DateTimeInterface) ? $rawDate2 : new DateTime((string) $rawDate2);

            return $date1->diff($date2);
        } catch (Exception) {
            return false;
        }
    }
}

if (!function_exists('days_diff_signed')) {
    function days_diff_signed(mixed $dt1, mixed $dt2): int|false
    {
        $diff = days_between($dt1, $dt2);
        if ($diff === false) {
            return false;
        }

        if (!is_int($diff->days)) {
            return false;
        }

        return $diff->invert === 1 ? -$diff->days : $diff->days;
    }
}

if (!function_exists('days_until')) {
    function days_until(mixed $date): int|false
    {
        return days_diff_signed(date('Y-m-d'), $date);
    }
}
