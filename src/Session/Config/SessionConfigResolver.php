<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Config;

final class SessionConfigResolver
{
    public function resolve(SessionConfigDefinition ...$definitions): SessionConfig
    {
        $driver = 'native';
        $cookie = 'LEMONADE_SESSION';
        $lifetime = 7200;
        $nativePath = 'sessions';
        $filePath = 'sessions';
        $databaseTable = 'sessions';
        $redisHost = '127.0.0.1';
        $redisPort = 6379;
        $redisDatabase = 0;
        $redisPassword = null;
        $redisPrefix = 'sess:';
        $redisTimeout = 2.5;

        foreach ($definitions as $definition) {
            $data = $definition->toArray();

            if (array_key_exists('driver', $data)) {
                $driver = $this->stringOr($data['driver'], $driver);
            }
            if (array_key_exists('cookie', $data)) {
                $cookie = $this->stringOr($data['cookie'], $cookie);
            }
            if (array_key_exists('lifetime', $data)) {
                $lifetime = max(1, $this->intOr($data['lifetime'], $lifetime));
            }

            $native = is_array($data['native'] ?? null) ? $data['native'] : [];
            if (array_key_exists('path', $native)) {
                $nativePath = $this->stringOr($native['path'], $nativePath);
            }

            $file = is_array($data['file'] ?? null) ? $data['file'] : [];
            if (array_key_exists('path', $file)) {
                $filePath = $this->stringOr($file['path'], $filePath);
            }

            $database = is_array($data['database'] ?? null) ? $data['database'] : [];
            if (array_key_exists('table', $database)) {
                $databaseTable = $this->stringOr($database['table'], $databaseTable);
            }

            $redis = is_array($data['redis'] ?? null) ? $data['redis'] : [];
            if (array_key_exists('host', $redis)) {
                $redisHost = $this->stringOr($redis['host'], $redisHost);
            }
            if (array_key_exists('port', $redis)) {
                $redisPort = $this->intOr($redis['port'], $redisPort);
            }
            if (array_key_exists('database', $redis)) {
                $redisDatabase = $this->intOr($redis['database'], $redisDatabase);
            }
            if (array_key_exists('password', $redis)) {
                $redisPassword = $this->nullableString($redis['password']);
            }
            if (array_key_exists('prefix', $redis)) {
                $redisPrefix = $this->stringOr($redis['prefix'], $redisPrefix);
            }
            if (array_key_exists('timeout', $redis)) {
                $redisTimeout = $this->floatOr($redis['timeout'], $redisTimeout);
            }
        }

        return new SessionConfig(
            driver: strtolower($driver),
            cookie: $cookie,
            lifetime: $lifetime,
            native: new SessionNativeConfig($nativePath),
            file: new SessionFileConfig($filePath),
            database: new SessionDatabaseConfig($databaseTable),
            redis: new SessionRedisConfig($redisHost, $redisPort, $redisDatabase, $redisPassword, $redisPrefix, $redisTimeout),
        );
    }

    private function stringOr(mixed $value, string $default): string
    {
        if (!is_scalar($value)) {
            return $default;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? $default : $normalized;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || !is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function intOr(mixed $value, int $default): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return (int) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }

    private function floatOr(mixed $value, float $default): float
    {
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return $default;
    }
}
