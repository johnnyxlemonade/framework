<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Pdo;

use Lemonade\Framework\Database\Connection\DatabaseConfig;
use Lemonade\Framework\Database\Exception\DatabaseException;

final class PdoDsnResolver
{
    public static function resolve(DatabaseConfig $config): string
    {
        $dsn = $config->dsn();

        if (is_string($dsn) && $dsn !== '') {
            return $dsn;
        }

        if ($config->database() === '') {
            throw DatabaseException::invalidConfiguration(
                'PDO requires "dsn" or non-empty "database" for MySQL DSN fallback.',
            );
        }

        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config->host(),
            $config->port(),
            $config->database(),
            $config->charset(),
        );
    }

    public static function isMysql(DatabaseConfig $config): bool
    {
        return str_starts_with(strtolower(self::resolve($config)), 'mysql:');
    }

    public static function isSqlite(DatabaseConfig $config): bool
    {
        return str_starts_with(strtolower(self::resolve($config)), 'sqlite:');
    }
}
