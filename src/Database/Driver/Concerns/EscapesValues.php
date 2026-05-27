<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Concerns;

trait EscapesValues
{
    public function escape(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (!$value instanceof \Stringable && !is_scalar($value)) {
            return "''";
        }

        return "'" . $this->escape_str((string) $value) . "'";
    }

    public function escape_like_str(string $value): string
    {
        return str_replace(
            ['!', '%', '_'],
            ['!!', '!%', '!_'],
            $value,
        );
    }

    abstract public function escape_str(string $value, bool $like = false): string;
}
