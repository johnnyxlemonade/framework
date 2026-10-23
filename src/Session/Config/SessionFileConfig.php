<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Config;

use InvalidArgumentException;

/**
 * Carries a local, traversal-free path selection for private file-backed session storage.
 */
final readonly class SessionFileConfig
{
    /**
     * Defines an absolute custom path or a path relative to the application's writable storage directory.
     *
     * @throws InvalidArgumentException When the path is empty, non-local, or contains traversal segments
     */
    public function __construct(
        private string $path,
    ) {
        $portable = str_replace('\\', '/', $path);
        $segments = explode('/', $portable);

        if (
            $path === ''
            || trim($path) !== $path
            || str_contains($path, "\0")
            || str_contains($path, '://')
            || preg_match('/[\x00-\x1F\x7F]/', $path) === 1
            || in_array('.', $segments, true)
            || in_array('..', $segments, true)
        ) {
            throw new InvalidArgumentException(
                'File session path must be a non-empty local path without traversal or control characters.',
            );
        }
    }

    /**
     * Returns the configured absolute path or writable-storage-relative path.
     */
    public function path(): string
    {
        return $this->path;
    }
}
