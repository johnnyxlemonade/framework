<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload;

use InvalidArgumentException;
use Lemonade\Framework\Core\Context\ApplicationContext;
use LogicException;

/**
 * Resolves the private upload root and rejects paths that escape it or traverse symbolic links.
 *
 * @internal UploadFactory and upload storage apply this boundary for configured and custom options.
 */
final readonly class UploadPathPolicy
{
    private const string STAGING_ROOT = 'uploads/staging';
    private const string CHUNK_ROOT = 'uploads/chunks';

    /**
     * Validates the fixed upload, staging, and chunk roots once for this application context.
     */
    public function __construct(private ApplicationContext $context)
    {
        $this->assertPrivateRootSeparated($this->root(), 'Private upload storage must be outside the public web root.');
        $this->assertPrivateRootSeparated($this->stagingRoot(), 'Upload staging must be outside the public web root.');
        $this->assertPrivateRootSeparated($this->chunkRoot(), 'Chunk upload storage must be outside the public web root.');
    }

    /**
     * Normalizes a configured directory and rejects absolute, traversing, control-character, or platform-ambiguous paths.
     *
     * @throws InvalidArgumentException When the directory is not a safe relative upload path
     */
    public static function normalizeRelativeDirectory(string $directory): string
    {
        $directory = trim($directory);

        if ($directory === '') {
            throw new InvalidArgumentException('Upload target_directory must be a non-empty relative path.');
        }

        if (
            str_contains($directory, "\0")
            || str_contains($directory, '\\')
            || str_contains($directory, '://')
            || preg_match('/^[A-Za-z]:/', $directory) === 1
            || str_starts_with($directory, '/')
            || preg_match('/[\x00-\x1F\x7F]/', $directory) === 1
        ) {
            throw new InvalidArgumentException('Upload target_directory must use safe relative path segments.');
        }

        $segments = explode('/', $directory);

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..' || str_contains($segment, ':')) {
                throw new InvalidArgumentException('Upload target_directory must use safe relative path segments.');
            }
        }

        return implode('/', $segments);
    }

    /**
     * Returns the absolute storage root assigned to all persisted uploads.
     */
    public function root(): string
    {
        return $this->context->uploadPath();
    }

    /**
     * Returns the private writable root used for immutable upload snapshots before validation and publication.
     */
    public function stagingRoot(): string
    {
        return $this->context->resolveWritablePath(self::STAGING_ROOT);
    }

    /**
     * Returns the private writable root used for incomplete chunk sessions.
     */
    public function chunkRoot(): string
    {
        return $this->context->resolveWritablePath(self::CHUNK_ROOT);
    }

    /**
     * Resolves a safe configured directory below its private upload root.
     */
    public function targetDirectory(
        string $relativeDirectory,
    ): string {
        $relativeDirectory = self::normalizeRelativeDirectory($relativeDirectory);
        $root = $this->root();
        $separator = str_contains($root, '\\') ? '\\' : DIRECTORY_SEPARATOR;

        return $root
            . $separator
            . str_replace('/', $separator, $relativeDirectory);
    }

    /**
     * Returns the persisted path relative to application storage.
     */
    public function storedRelativeDirectory(
        string $relativeDirectory,
    ): string {
        $relativeDirectory = self::normalizeRelativeDirectory($relativeDirectory);

        return $this->context->uploadRelativePath($relativeDirectory);
    }

    /**
     * Rejects runtime targets outside their assigned root or behind any symbolic-link path component.
     *
     * @throws InvalidArgumentException When the target cannot be safely contained
     */
    public function assertTargetDirectory(
        string $targetDirectory,
    ): void {
        $this->assertDirectoryContained(
            $targetDirectory,
            $this->root(),
        );
    }

    /**
     * Rejects chunk storage paths outside the private root or behind symbolic-link components.
     *
     * @throws InvalidArgumentException When the directory cannot be safely contained
     */
    public function assertChunkDirectory(string $directory): void
    {
        $this->assertDirectoryContained(
            $directory,
            $this->chunkRoot(),
        );
    }

    private function assertDirectoryContained(string $directory, string $configuredRoot): void
    {
        $portableTarget = str_replace('\\', '/', $directory);
        $segments = explode('/', $portableTarget);

        if (
            !$this->isAbsolute($directory)
            || str_contains($directory, "\0")
            || str_contains($directory, '://')
            || in_array('.', $segments, true)
            || in_array('..', $segments, true)
        ) {
            throw new InvalidArgumentException('Upload target directory must be an absolute local path.');
        }

        $root = $this->canonicalCandidate($configuredRoot);
        $target = $this->canonicalCandidate($directory);

        if (!$this->isSameOrDescendant($target, $root)) {
            throw new InvalidArgumentException('Upload target directory escapes its configured storage root.');
        }

        $this->assertNoSymbolicLinkComponents($directory, $configuredRoot);
    }

    private function assertPrivateRootSeparated(string $privatePath, string $message): void
    {
        $publicRoot = $this->canonicalCandidate($this->context->publicPath());
        $privateRoot = $this->canonicalCandidate($privatePath);

        if (
            $this->isSameOrDescendant($privateRoot, $publicRoot)
            || $this->isSameOrDescendant($publicRoot, $privateRoot)
        ) {
            throw new LogicException($message);
        }
    }

    private function canonicalCandidate(string $path): string
    {
        $normalized = $this->normalizeAbsolute($path);
        $cursor = $normalized;
        $suffix = [];

        while (!file_exists($cursor) && !is_link($cursor)) {
            $parent = dirname($cursor);

            if ($parent === $cursor) {
                return $normalized;
            }

            array_unshift($suffix, basename($cursor));
            $cursor = $parent;
        }

        $resolved = realpath($cursor);

        if ($resolved === false) {
            return $normalized;
        }

        if ($suffix !== []) {
            $resolved .= DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $suffix);
        }

        return $this->normalizeAbsolute($resolved);
    }

    private function normalizeAbsolute(string $path): string
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $prefix = DIRECTORY_SEPARATOR;

        if (DIRECTORY_SEPARATOR === '\\' && str_starts_with($path, '\\\\')) {
            $prefix = '\\\\';
            $path = ltrim($path, DIRECTORY_SEPARATOR);
        } elseif (preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1) {
            $prefix = strtoupper(substr($path, 0, 2)) . DIRECTORY_SEPARATOR;
            $path = substr($path, 3);
        } else {
            $path = ltrim($path, DIRECTORY_SEPARATOR);
        }

        $segments = [];

        foreach (explode(DIRECTORY_SEPARATOR, $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        $normalized = $prefix . implode(DIRECTORY_SEPARATOR, $segments);

        if (
            $normalized === DIRECTORY_SEPARATOR
            || $normalized === '\\\\'
            || preg_match('/^[A-Za-z]:[\/\\\\]$/', $normalized) === 1
        ) {
            return $normalized;
        }

        return rtrim($normalized, DIRECTORY_SEPARATOR);
    }

    private function isSameOrDescendant(string $path, string $root): bool
    {
        $path = $this->comparablePath($path);
        $root = $this->comparablePath($root);

        return $path === $root || str_starts_with($path, $root . DIRECTORY_SEPARATOR);
    }

    private function comparablePath(string $path): string
    {
        $path = $this->normalizeAbsolute($path);

        return DIRECTORY_SEPARATOR === '\\' ? strtolower($path) : $path;
    }

    private function assertNoSymbolicLinkComponents(string $path, string $root): void
    {
        $cursor = $this->normalizeAbsolute($path);
        $root = $this->normalizeAbsolute($root);

        while (true) {
            if (is_link($cursor)) {
                throw new InvalidArgumentException('Upload target directory cannot contain symbolic links.');
            }

            if ($this->comparablePath($cursor) === $this->comparablePath($root)) {
                return;
            }

            $parent = dirname($cursor);

            if ($parent === $cursor) {
                throw new InvalidArgumentException('Upload target directory escapes its configured storage root.');
            }

            $cursor = $parent;
        }
    }

    private function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\\\')
            || preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1;
    }
}
