<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Exception;

use RuntimeException;
use Throwable;

/**
 * Represents invalid database configuration or use that prevents a driver operation from proceeding.
 */
final class DatabaseException extends RuntimeException
{
    /**
     * Creates an exception for a failed physical connection while retaining the driver error as its cause.
     */
    public static function connectionFailed(string $message, ?Throwable $previous = null): self
    {
        return new self(
            message: 'Database connection failed: ' . $message,
            code: 0,
            previous: $previous,
        );
    }

    /**
     * Creates an exception that preserves both failed SQL and the original driver failure.
     */
    public static function queryFailed(string $sql, string $message, ?Throwable $previous = null): self
    {
        return new self(
            message: 'Database query failed: ' . $message . PHP_EOL . 'SQL: ' . $sql,
            code: 0,
            previous: $previous,
        );
    }

    /**
     * Creates an exception for a driver lacking a registered framework implementation.
     */
    public static function unsupportedDriver(string $driver): self
    {
        return new self(
            message: 'Unsupported database driver: ' . $driver,
        );
    }

    /**
     * Creates an exception for configuration that cannot form a usable database service.
     */
    public static function invalidConfiguration(string $message): self
    {
        return new self(
            message: 'Invalid database configuration: ' . $message,
        );
    }
}
