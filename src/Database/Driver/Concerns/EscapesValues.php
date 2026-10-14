<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Concerns;

/**
 * Shares value escaping adapters that delegate SQL literal quoting to the active connection.
 */
trait EscapesValues
{
    /**
     * Converts scalar values to SQL literals and delegates string escaping to the consuming driver.
     */
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

    /**
     * Escapes the wildcard and escape characters used by LIKE expressions before a value is bound or quoted.
     */
    public function escape_like_str(string $value): string
    {
        return str_replace(
            ['!', '%', '_'],
            ['!!', '!%', '!_'],
            $value,
        );
    }

    /**
     * Escapes one string value according to the consuming driver's SQL literal rules.
     */
    abstract public function escape_str(string $value, bool $like = false): string;
}
