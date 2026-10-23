<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Context;

/**
 * Carries the immutable environment, path, and debug boundaries resolved for one application bootstrap.
 */
final readonly class ApplicationContext
{
    /**
     * Captures the resolved runtime values shared by HTTP and CLI infrastructure services.
     */
    public function __construct(
        private Environment $environment,
        private Path $paths,
        private DebugMode $debug,
    ) {
    }

    /**
     * Returns the normalized application environment used for runtime policy decisions.
     */
    public function environment(): Environment
    {
        return $this->environment;
    }

    /**
     * Returns the immutable path resolver rooted at the configured application directory.
     */
    public function paths(): Path
    {
        return $this->paths;
    }

    /**
     * Returns the normalized debug-mode value object.
     */
    public function debugMode(): DebugMode
    {
        return $this->debug;
    }

    /**
     * Reports whether development diagnostics are enabled for this application.
     */
    public function debug(): bool
    {
        return $this->debug->isEnabled();
    }

    /**
     * Reports whether development diagnostics are enabled for this application.
     */
    public function isDebug(): bool
    {
        return $this->debug->isEnabled();
    }

    /**
     * Returns the absolute application root established during bootstrap.
     */
    public function basePath(): string
    {
        return $this->paths->base();
    }

    /**
     * Resolves an absolute path or an application-root-relative path without asserting containment.
     */
    public function path(string $path = ''): string
    {
        return $this->paths->resolve($path);
    }

    /**
     * Resolves a path below the conventional application source directory.
     */
    public function appPath(string $path = ''): string
    {
        return $this->paths->app($path);
    }

    /**
     * Resolves a path below the application configuration directory.
     */
    public function configPath(string $path = ''): string
    {
        return $this->paths->config($path);
    }

    /**
     * Resolves a path below application storage.
     */
    public function storagePath(string $path = ''): string
    {
        return $this->paths->storage($path);
    }

    /**
     * Resolves a path below the configured public web root.
     */
    public function publicPath(string $path = ''): string
    {
        return $this->paths->publicPath($path);
    }

    /**
     * Preserves an absolute path or resolves a relative path below application storage.
     *
     * This method performs path composition only; consumers that persist untrusted data must apply their own
     * containment and symbolic-link policy.
     */
    public function resolveStoragePath(string $path = ''): string
    {
        if ($this->paths->isAbsolute($path)) {
            return $this->paths->resolve($path);
        }

        return $this->storagePath($this->trimRelativePath($path));
    }

    /**
     * Preserves an absolute path or resolves a relative path below private writable storage.
     */
    public function resolveWritablePath(string $path = ''): string
    {
        return $this->resolveStorageSubPath('writable', $path);
    }

    /**
     * Preserves an absolute path or resolves a relative path below writable log storage.
     */
    public function resolveLogPath(string $path = ''): string
    {
        return $this->resolveStorageSubPath('writable/logs', $path);
    }

    /**
     * Preserves an absolute path or resolves a relative path below writable session storage.
     */
    public function resolveSessionPath(string $path = 'sessions'): string
    {
        return $this->resolveStorageSubPath('writable', $path);
    }

    /**
     * Resolves an upload path using the public upload storage convention.
     */
    public function resolveUploadPath(string $path = ''): string
    {
        return $this->uploadPath($path);
    }

    /**
     * Preserves an absolute path or resolves a relative path below the public uploads directory.
     */
    public function uploadPath(string $path = ''): string
    {
        return $this->resolvePublicSubPath('uploads', $path);
    }

    /**
     * Preserves an absolute path or resolves a relative path below application cache storage.
     */
    public function resolveCachePath(string $path = ''): string
    {
        return $this->resolveStorageSubPath('cache', $path);
    }

    /**
     * Returns a portable forward-slash path relative to the public root for an upload location.
     */
    public function uploadRelativePath(string $path = ''): string
    {
        return $this->joinRelativePath('uploads', $path);
    }

    /**
     * Reports whether production runtime behavior is active.
     */
    public function isProduction(): bool
    {
        return $this->environment->isProduction();
    }

    /**
     * Reports whether development runtime behavior is active.
     */
    public function isDevelopment(): bool
    {
        return $this->environment->isDevelopment();
    }

    /**
     * Reports whether testing runtime behavior is active.
     */
    public function isTesting(): bool
    {
        return $this->environment->isTesting();
    }

    private function joinRelativePath(string $base, string $path = ''): string
    {
        $base = $this->trimRelativePath($base, '/');
        $path = $this->trimRelativePath($path, '/');

        if ($path === '') {
            return $base;
        }

        return $base . '/' . $path;
    }

    private function resolvePublicSubPath(string $base, string $path = ''): string
    {
        if ($this->paths->isAbsolute($path)) {
            return $this->paths->resolve($path);
        }

        $base = $this->trimRelativePath($base);
        $path = $this->trimRelativePath($path);

        if ($path === '') {
            return $this->publicPath($base);
        }

        return $this->publicPath($base . $this->pathSeparator() . $path);
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

        return $this->storagePath($base . $this->pathSeparator() . $path);
    }

    private function trimRelativePath(string $path, ?string $separator = null): string
    {
        $separator ??= $this->pathSeparator();

        return trim(str_replace(['/', '\\'], $separator, $path), '/\\');
    }

    private function pathSeparator(): string
    {
        return str_contains($this->basePath(), '\\') ? '\\' : '/';
    }
}
