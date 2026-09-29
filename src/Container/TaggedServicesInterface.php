<?php

declare(strict_types=1);

namespace Lemonade\Framework\Container;

/**
 * Resolves an explicitly declared service collection from the frozen container plan.
 */
interface TaggedServicesInterface
{
    /**
     * @param non-empty-string $tag
     * @return iterable<string, object>
     */
    public function tagged(string $tag): iterable;
}
