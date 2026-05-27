<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Context;

final class ApplicationContext
{
    public function __construct(
        private readonly Environment $environment,
        private readonly Path $paths,
        private readonly DebugMode $debug,
    ) {}

    public function environment(): Environment
    {
        return $this->environment;
    }

    public function paths(): Path
    {
        return $this->paths;
    }

    public function debugMode(): DebugMode
    {
        return $this->debug;
    }

    public function debug(): bool
    {
        return $this->debug->isEnabled();
    }

    public function isDebug(): bool
    {
        return $this->debug->isEnabled();
    }

    public function basePath(): string
    {
        return $this->paths->base();
    }

    public function path(string $path = ''): string
    {
        return $this->paths->resolve($path);
    }

    public function appPath(string $path = ''): string
    {
        return $this->paths->app($path);
    }

    public function configPath(string $path = ''): string
    {
        return $this->paths->config($path);
    }

    public function storagePath(string $path = ''): string
    {
        return $this->paths->storage($path);
    }

    public function resolveStoragePath(string $path = ''): string
    {
        if ($this->paths->isAbsolute($path)) {
            return $this->paths->resolve($path);
        }

        return $this->storagePath($this->trimRelativePath($path));
    }

    public function resolveWritablePath(string $path = ''): string
    {
        return $this->resolveStorageSubPath('writable', $path);
    }

    public function resolveLogPath(string $path = ''): string
    {
        return $this->resolveStorageSubPath('writable/logs', $path);
    }

    public function resolveSessionPath(string $path = 'sessions'): string
    {
        return $this->resolveStorageSubPath('writable', $path);
    }

    public function resolveUploadPath(string $path = ''): string
    {
        return $this->resolveStorageSubPath('uploads', $path);
    }

    public function resolveCachePath(string $path = ''): string
    {
        return $this->resolveStorageSubPath('cache', $path);
    }

    public function uploadRelativePath(string $path = ''): string
    {
        return $this->joinRelativePath('uploads', $path);
    }

    public function isProduction(): bool
    {
        return $this->environment->isProduction();
    }

    public function isDevelopment(): bool
    {
        return $this->environment->isDevelopment();
    }

    public function isTesting(): bool
    {
        return $this->environment->isTesting();
    }

    private function joinRelativePath(string $base, string $path = ''): string
    {
        $base = $this->trimRelativePath($base);
        $path = $this->trimRelativePath($path);

        if ($path === '') {
            return $base;
        }

        return $base . '/' . $path;
    }

    private function resolveStorageSubPath(string $base, string $path = ''): string
    {
        if ($this->paths->isAbsolute($path)) {
            return $this->paths->resolve($path);
        }

        $base = $this->trimRelativePath($base);
        $path = $this->trimRelativePath($path);

        if ($path === '') {
            return $this->storagePath($base);
        }

        return $this->storagePath($base . DIRECTORY_SEPARATOR . $path);
    }

    private function trimRelativePath(string $path): string
    {
        return trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), '/\\');
    }
}
