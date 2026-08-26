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
        $resolvedBasePath = $this->normalizePath($resolvedBasePath);

        $publicPath = $this->resolvePublicPath($resolvedBasePath, $env, $server);

        return new ApplicationContext(
            environment: $environment,
            paths: new Path($resolvedBasePath, $publicPath),
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
        $separator = $this->separatorFor($path);

        return rtrim(str_replace(['/', '\\'], $separator, $path), '/\\');
    }

    /**
     * @param array<string, mixed> $env
     * @param array<string, mixed> $server
     */
    private function resolvePublicPath(string $basePath, array $env, array $server): string
    {
        $publicPathValue = $this->value('APP_PUBLIC_PATH', $env, $server);
        if (is_scalar($publicPathValue)) {
            $normalizedOverride = trim((string) $publicPathValue);
            if ($normalizedOverride !== '') {
                return $this->resolveAgainstBase($basePath, $normalizedOverride);
            }
        }

        $scriptFilename = $this->value('SCRIPT_FILENAME', $env, $server);
        $scriptPath = is_scalar($scriptFilename) ? trim((string) $scriptFilename) : '';
        if ($scriptPath !== '') {
            $resolvedFromScript = $this->resolvePublicPathFromScript($basePath, $scriptPath);
            if ($resolvedFromScript !== null) {
                return $resolvedFromScript;
            }
        }

        $conventionalPublicPath = $basePath . $this->separatorFor($basePath) . 'public';
        if (is_dir($conventionalPublicPath)) {
            return $conventionalPublicPath;
        }

        return $basePath;
    }

    private function resolveAgainstBase(string $basePath, string $path): string
    {
        if ($this->isAbsolutePath($path)) {
            return $this->normalizePath($path);
        }

        return $basePath . $this->separatorFor($basePath) . ltrim($this->normalizePath($path), '/\\');
    }

    private function resolvePublicPathFromScript(string $basePath, string $scriptFilename): ?string
    {
        $scriptFilename = $this->resolveAgainstBase($basePath, $scriptFilename);
        $normalizedScript = $this->normalizePath($scriptFilename);

        if (strtolower($this->portableBasename($normalizedScript)) !== 'index.php') {
            return null;
        }

        $scriptDirectory = $this->portableDirname($normalizedScript);
        if ($scriptDirectory === '' || $scriptDirectory === '.') {
            return null;
        }

        $scriptDirectory = $this->normalizePath($scriptDirectory);

        if (!$this->isSameOrDescendantPath($basePath, $scriptDirectory)) {
            return null;
        }

        return $scriptDirectory;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\\\')
            || preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1;
    }

    private function isSameOrDescendantPath(string $basePath, string $path): bool
    {
        $normalizedBase = $this->normalizeComparablePath($basePath);
        $normalizedPath = $this->normalizeComparablePath($path);

        return $normalizedPath === $normalizedBase
            || str_starts_with($normalizedPath, $normalizedBase . $this->separatorFor($normalizedBase));
    }

    private function normalizeComparablePath(string $path): string
    {
        $normalized = $this->normalizePath($path);

        if (preg_match('/^[A-Za-z]:/', $normalized) === 1) {
            return strtolower($normalized);
        }

        return $normalized;
    }

    private function separatorFor(string $path): string
    {
        if (str_contains($path, '\\') || str_starts_with($path, '\\\\') || preg_match('/^[A-Za-z]:/', $path) === 1) {
            return '\\';
        }

        return '/';
    }

    private function portableBasename(string $path): string
    {
        $portablePath = str_replace('\\', '/', $path);

        return basename($portablePath);
    }

    private function portableDirname(string $path): string
    {
        $separator = $this->separatorFor($path);
        $portablePath = str_replace('\\', '/', $path);
        $directory = dirname($portablePath);

        if ($directory === '.' || $directory === '') {
            return $directory;
        }

        return $separator === '\\'
            ? str_replace('/', '\\', $directory)
            : $directory;
    }
}
