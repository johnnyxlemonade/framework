<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Config;

/**
 * Carries the resolved templates used to render framework HTTP error pages.
 */
final readonly class ErrorConfig
{
    /**
     * Initializes an immutable error-template snapshot.
     */
    public function __construct(
        public string $notFoundView,
        public string $internalServerErrorView,
    ) {
    }
}
