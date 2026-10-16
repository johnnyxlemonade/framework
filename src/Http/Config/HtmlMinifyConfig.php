<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Config;

/**
 * Carries the resolved switch controlling HTML response minification.
 */
final readonly class HtmlMinifyConfig
{
    /**
     * Initializes the immutable minification setting used by middleware.
     */
    public function __construct(
        public bool $enabled,
    ) {
    }
}
