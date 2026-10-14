<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Config;

/**
 * Carries the writable path selection for native PHP session storage
 */
final readonly class SessionNativeConfig
{
    /**
     * Defines the path relative to the application's writable storage directory
     */
    public function __construct(
        private string $path,
    ) {
    }

    /**
     * Returns the path relative to the application's writable storage directory.
     */
    public function path(): string
    {
        return $this->path;
    }
}
