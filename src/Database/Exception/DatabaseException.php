<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Exception;

use RuntimeException;
use Throwable;

final class DatabaseException extends RuntimeException
{
    public static function connectionFailed(string $message, ?Throwable $previous = null): self
    {
        return new self(
            message: 'Database connection failed: ' . $message,
            code: 0,
            previous: $previous,
        );
    }

    public static function queryFailed(string $sql, string $message, ?Throwable $previous = null): self
    {
        return new self(
            message: 'Database query failed: ' . $message . PHP_EOL . 'SQL: ' . $sql,
            code: 0,
            previous: $previous,
        );
    }

    public static function unsupportedDriver(string $driver): self
    {
        return new self(
            message: 'Unsupported database driver: ' . $driver,
        );
    }

    public static function invalidConfiguration(string $message): self
    {
        return new self(
            message: 'Invalid database configuration: ' . $message,
        );
    }
}
