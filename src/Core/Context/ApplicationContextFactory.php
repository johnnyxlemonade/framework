<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Context;

use Lemonade\Framework\Support\EnvFileLoader;

final class ApplicationContextFactory
{
    /**
     * @param array<string, mixed> $env
     * @param array<string, mixed> $server
     */
    public function create(
        string $basePath,
        array $env = [],
        array $server = [],
    ): ApplicationContext {
        $env = $this->normalizeAssoc($env);
        $server = $this->normalizeAssoc($server);

        $environmentValue = $this->value('APP_ENV', $env, $server);
        $environmentName = is_scalar($environmentValue) ? (string) $environmentValue : 'production';
        $environment = Environment::fromString(
            $environmentName,
        );

        $debug = $this->toBool(
            $this->value('APP_DEBUG', $env, $server),
            $environment->isDebugDefault(),
        );

        $basePathValue = $this->value('APP_BASE_PATH', $env, $server);
        $resolvedBasePath = is_scalar($basePathValue) ? (string) $basePathValue : $basePath;

        return new ApplicationContext(
            environment: $environment,
            paths: new Path($this->normalizePath($resolvedBasePath)),
            debug: new DebugMode($debug),
        );
    }

    public function fromGlobals(string $basePath): ApplicationContext
    {
        $basePath = $this->normalizePath($basePath);

        (new EnvFileLoader())->load(
            $basePath . DIRECTORY_SEPARATOR . '.env',
        );

        return $this->create(
            basePath: $basePath,
            env: $this->normalizeAssoc($_ENV),
            server: $this->normalizeAssoc($_SERVER),
        );
    }

    /**
     * @param array<mixed> $values
     * @return array<string, mixed>
     */
    private function normalizeAssoc(array $values): array
    {
        $normalized = [];

        foreach ($values as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $env
     * @param array<string, mixed> $server
     */
    private function value(string $key, array $env, array $server): mixed
    {
        if (array_key_exists($key, $env)) {
            return $env[$key];
        }

        if (array_key_exists($key, $server)) {
            return $server[$key];
        }

        $value = getenv($key);

        return $value === false ? null : $value;
    }

    private function toBool(mixed $value, bool $default = false): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            $result = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            return $result ?? $default;
        }

        return $default;
    }

    private function normalizePath(string $path): string
    {
        return rtrim($path, '/\\');
    }
}
