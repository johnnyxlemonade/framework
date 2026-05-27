<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Request;

final class InputSanitizer
{
    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function sanitizeArray(array $input): array
    {
        $clean = [];
        foreach ($input as $key => $value) {
            $safeKey = $this->sanitizeKey($key);
            $clean[$safeKey] = $this->sanitizeValue($value);
        }

        return $clean;
    }

    private function sanitizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $key => $item) {
                if (is_string($key)) {
                    $normalized[$key] = $item;
                }
            }

            return $this->sanitizeArray($normalized);
        }

        if (!is_string($value)) {
            return $value;
        }

        $value = $this->removeInvisibleCharacters($value);
        $value = str_replace("\0", '', $value);

        if (!mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }

        return $value;
    }

    private function sanitizeKey(string $key): string
    {
        $key = $this->removeInvisibleCharacters($key);
        $key = str_replace("\0", '', $key);
        return preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $key) ?? '';
    }

    private function removeInvisibleCharacters(string $value): string
    {
        do {
            $before = $value;
            $value = preg_replace('/%0[0-8bcef]/i', '', $value) ?? $value;
            $value = preg_replace('/%1[0-9a-f]/i', '', $value) ?? $value;
            $value = preg_replace('/%7f/i', '', $value) ?? $value;
            $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S', '', $value) ?? $value;
        } while ($before !== $value);

        return $value;
    }
}
