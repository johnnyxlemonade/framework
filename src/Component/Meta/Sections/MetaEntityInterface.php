<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Meta\Sections;

/**
 * Defines a metadata section that contributes HTML tags to a page head.
 */
interface MetaEntityInterface
{
    /**
     * Renders this section's non-empty tags as HTML.
     */
    public function render(): string;
}
