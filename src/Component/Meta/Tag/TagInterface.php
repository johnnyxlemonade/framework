<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Meta\Tag;

/**
 * Defines an HTML metadata tag that can suppress itself when its value is empty.
 */
interface TagInterface
{
    /**
     * Renders the tag or returns an empty string when it has no renderable value.
     */
    public function render(): string;
}
