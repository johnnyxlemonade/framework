<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Meta\Config;

/**
 * Holds the resolved defaults applied when page metadata leaves a standard value unset.
 */
final readonly class MetaConfig
{
    /**
     * Initializes the immutable defaults used by the metadata component.
     */
    public function __construct(
        public string $websiteName,
        public string $charset,
        public string $viewport,
        public string $rating,
        public string $titleSeparator,
    ) {
    }
}
