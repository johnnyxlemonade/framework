<?php

declare(strict_types=1);

namespace Lemonade\Framework\Mime;

/**
 * Supplies deterministic application-owned MIME families during catalog composition.
 */
interface MimeTypeDefinitionProviderInterface
{
    /**
     * Returns the additional definitions contributed by this capability in deterministic order
     *
     * @return iterable<MimeTypeDefinition>
     */
    public function definitions(): iterable;
}
