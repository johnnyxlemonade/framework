<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Logging;

use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Logging\Exception\LogStorageException;

/**
 * Resolves built-in channels inside application storage and outside the public web root.
 */
final readonly class LogFilePathResolver
{
    /**
     * Binds canonical log paths to the active application context.
     */
    public function __construct(
        private ApplicationContext $context,
    ) {
    }

    /**
     * Returns a safe base filename after validating its storage and public-root boundaries.
     *
     * @throws LogStorageException When the configured path crosses a storage, public-root, or symlink boundary.
     */
    public function resolve(string $channel): string
    {
        if (preg_match('/^[a-z][a-z0-9_-]*$/D', $channel) !== 1) {
            throw new LogStorageException(sprintf(
                'Log channel "%s" cannot be mapped to a safe filename.',
                $channel,
            ));
        }

        $storageRoot = $this->assertAbsoluteLocalPath(
            $this->context->storagePath(),
            'Application storage root',
        );
        $publicRoot = $this->assertAbsoluteLocalPath(
            $this->context->publicPath(),
            'Public web root',
        );
        $directory = $this->assertAbsoluteLocalPath(
            $this->context->resolveLogPath(),
            'Log directory',
        );

        $this->assertContained($directory, $storageRoot);
        $this->assertSeparatedFromPublicRoot($directory, $publicRoot);

        if (is_link($directory) || is_link(dirname($directory))) {
            throw new LogStorageException('Log directory path cannot contain a symbolic link.');
        }

        $resolvedStorageRoot = @realpath($storageRoot);
        $resolvedBoundary = @realpath($directory);

        if (!is_string($resolvedBoundary)) {
            $resolvedBoundary = @realpath(dirname($directory));
        }

        if (!is_string($resolvedBoundary)) {
            $resolvedBoundary = $resolvedStorageRoot;
        }

        $resolvedPublicRoot = @realpath($publicRoot);

        if (is_string($resolvedBoundary) && is_string($resolvedPublicRoot)) {
            $this->assertSeparatedFromPublicRoot($resolvedBoundary, $resolvedPublicRoot);
        }

        return $directory . DIRECTORY_SEPARATOR . $channel . '.log';
    }

    private function assertAbsoluteLocalPath(string $path, string $label): string
    {
        $path = rtrim($path, '/\\');
        $portable = str_replace('\\', '/', $path);
        $segments = explode('/', $portable);

        if (
            $path === ''
            || str_contains($path, "\0")
            || str_contains($path, '://')
            || preg_match('/[\x00-\x1F\x7F]/', $path) === 1
            || in_array('.', $segments, true)
            || in_array('..', $segments, true)
            || !$this->isAbsolutePath($path)
        ) {
            throw new LogStorageException($label . ' must be an absolute local path without traversal.');
        }

        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }

    private function assertContained(string $path, string $storageRoot): void
    {
        $path = $this->comparablePath($path);
        $storageRoot = $this->comparablePath($storageRoot);

        if ($path !== $storageRoot && !str_starts_with($path, $storageRoot . DIRECTORY_SEPARATOR)) {
            throw new LogStorageException('Log directory escapes application storage.');
        }
    }

    private function assertSeparatedFromPublicRoot(string $path, string $publicRoot): void
    {
        $path = $this->comparablePath($path);
        $publicRoot = $this->comparablePath($publicRoot);
        $overlaps = $path === $publicRoot
            || str_starts_with($path, $publicRoot . DIRECTORY_SEPARATOR)
            || str_starts_with($publicRoot, $path . DIRECTORY_SEPARATOR);

        if ($overlaps) {
            throw new LogStorageException('Log storage must be separated from the public web root.');
        }
    }

    private function comparablePath(string $path): string
    {
        $path = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);

        if ($path === '') {
            return DIRECTORY_SEPARATOR;
        }

        return DIRECTORY_SEPARATOR === '\\' ? strtolower($path) : $path;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\\\')
            || preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1;
    }
}
