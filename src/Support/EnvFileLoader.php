<?php

declare(strict_types=1);

namespace Lemonade\Framework\Support;

final class EnvFileLoader
{
    public function load(string $file): void
    {
        if (!is_file($file) || !is_readable($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);

            $key = trim($key);
            $value = $this->normalizeValue(trim($value));

            if ($key === '') {
                continue;
            }

            if ($this->exists($key)) {
                continue;
            }

            $this->set($key, $value);
        }
    }

    private function exists(string $key): bool
    {
        return array_key_exists($key, $_ENV)
            || array_key_exists($key, $_SERVER)
            || getenv($key) !== false;
    }

    private function set(string $key, string $value): void
    {
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;

        putenv($key . '=' . $value);
    }

    private function normalizeValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $first = $value[0];
        $last = $value[strlen($value) - 1];

        if (
            ($first === '"' && $last === '"')
            || ($first === "'" && $last === "'")
        ) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}
