<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Logging;

use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Logging\Exception\LogStorageException;
use Throwable;

/**
 * Resolves built-in channels into a private application-storage directory outside the public web root.
 */
final class LogFilePathResolver
{
    /**
     * Binds canonical log paths to the active application context.
     */
    public function __construct(
        private readonly ApplicationContext $context,
    ) {
    }

    /**
     * Returns a safe base filename after securing its storage boundary and rejecting unsafe paths.
     *
     * @throws LogStorageException When containment, symlink, or permission policy cannot be enforced.
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
        $this->assertNoSymbolicLinkComponents($storageRoot);
        $this->ensureStorageRoot($storageRoot);
        $this->ensurePrivateDirectory($directory, $storageRoot);
        $this->assertNoSymbolicLinkComponents($directory);
        $this->assertCanonicalContainment($directory, $storageRoot);
        $this->assertSeparatedFromPublicRoot($directory, $publicRoot);

        return $directory . DIRECTORY_SEPARATOR . $channel . '.log';
    }

    private function ensureStorageRoot(string $storageRoot): void
    {
        if (!is_dir($storageRoot) && !@mkdir($storageRoot, 0700, true) && !is_dir($storageRoot)) {
            throw new LogStorageException('Application storage root cannot be created for logging.');
        }

        clearstatcache(true, $storageRoot);
        $stat = @lstat($storageRoot);

        if (
            !is_array($stat)
            || is_link($storageRoot)
            || ($stat['mode'] & 0170000) !== 0040000
        ) {
            throw new LogStorageException('Application storage root is not a safe directory for logging.');
        }

        $permissions = $stat['mode'];

        if (($permissions & 0022) === 0) {
            return;
        }

        $securedMode = ($permissions & 0777) & ~0022;

        if (!@chmod($storageRoot, $securedMode)) {
            throw new LogStorageException('Application storage root permissions cannot be restricted.');
        }

        clearstatcache(true, $storageRoot);
        $secured = @lstat($storageRoot);

        if (
            !is_array($secured)
            || is_link($storageRoot)
            || $secured['dev'] !== $stat['dev']
            || $secured['ino'] !== $stat['ino']
            || ($secured['mode'] & 0022) !== 0
        ) {
            throw new LogStorageException('Application storage root remains writable by other users.');
        }
    }

    private function ensurePrivateDirectory(string $directory, string $storageRoot): void
    {
        $relative = ltrim(substr($directory, strlen(rtrim($storageRoot, '/\\'))), '/\\');
        $segments = preg_split('~[\\\\/]~', $relative, -1, PREG_SPLIT_NO_EMPTY);
        $cursor = rtrim($storageRoot, '/\\');

        if (!is_array($segments)) {
            throw new LogStorageException('Log directory path cannot be segmented safely.');
        }

        foreach ($segments as $segment) {
            $cursor .= DIRECTORY_SEPARATOR . $segment;

            if (is_link($cursor)) {
                throw new LogStorageException('Log directory path cannot contain symbolic links.');
            }

            if (!is_dir($cursor)) {
                try {
                    $created = @mkdir($cursor, 0700);
                } catch (Throwable $exception) {
                    throw new LogStorageException(
                        sprintf('Log directory "%s" cannot be created securely.', $cursor),
                        previous: $exception,
                    );
                }

                if (!$created && !is_dir($cursor)) {
                    throw new LogStorageException(sprintf(
                        'Log directory "%s" cannot be created securely.',
                        $cursor,
                    ));
                }
            }

            clearstatcache(true, $cursor);
            $stat = @lstat($cursor);

            if (
                !is_array($stat)
                || is_link($cursor)
                || ($stat['mode'] & 0170000) !== 0040000
            ) {
                throw new LogStorageException(sprintf('Log directory "%s" is unsafe.', $cursor));
            }

            if (!@chmod($cursor, 0700)) {
                throw new LogStorageException(sprintf(
                    'Log directory "%s" permissions cannot be restricted to mode 0700.',
                    $cursor,
                ));
            }

            clearstatcache(true, $cursor);
            $secured = @lstat($cursor);

            if (
                !is_array($secured)
                || is_link($cursor)
                || $secured['dev'] !== $stat['dev']
                || $secured['ino'] !== $stat['ino']
                || ($secured['mode'] & 0777) !== 0700
            ) {
                throw new LogStorageException(sprintf(
                    'Log directory "%s" permissions cannot be restricted to mode 0700.',
                    $cursor,
                ));
            }
        }
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

    private function assertCanonicalContainment(string $path, string $storageRoot): void
    {
        $resolvedPath = @realpath($path);
        $resolvedRoot = @realpath($storageRoot);

        if (!is_string($resolvedPath) || !is_string($resolvedRoot)) {
            throw new LogStorageException('Canonical log storage boundary cannot be resolved.');
        }

        $this->assertContained($resolvedPath, $resolvedRoot);
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

        $resolvedPath = @realpath($path);
        $resolvedPublic = @realpath($publicRoot);

        if (!is_string($resolvedPath) || !is_string($resolvedPublic)) {
            return;
        }

        $resolvedPath = $this->comparablePath($resolvedPath);
        $resolvedPublic = $this->comparablePath($resolvedPublic);
        $canonicalOverlap = $resolvedPath === $resolvedPublic
            || str_starts_with($resolvedPath, $resolvedPublic . DIRECTORY_SEPARATOR)
            || str_starts_with($resolvedPublic, $resolvedPath . DIRECTORY_SEPARATOR);

        if ($canonicalOverlap) {
            throw new LogStorageException('Canonical log storage overlaps the public web root.');
        }
    }

    private function assertNoSymbolicLinkComponents(string $path): void
    {
        $cursor = $path;

        while (true) {
            if (is_link($cursor)) {
                throw new LogStorageException('Log directory path cannot contain symbolic links.');
            }

            $parent = dirname($cursor);
            if ($parent === $cursor) {
                return;
            }

            $cursor = $parent;
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
