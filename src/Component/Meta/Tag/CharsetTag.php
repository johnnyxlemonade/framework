<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Meta\Tag;

use function htmlspecialchars;
use function sprintf;

/**
 * Renders an escaped character-set declaration when one is configured.
 */
final readonly class CharsetTag implements TagInterface
{
    /**
     * Initializes the optional character-set value.
     */
    public function __construct(
        private ?string $charset,
    ) {
    }

    /**
     * Renders the character-set declaration or suppresses it for empty content.
     */
    public function render(): string
    {
        if ($this->charset === null || $this->charset === '') {
            return '';
        }

        return sprintf(
            '<meta charset="%s">',
            htmlspecialchars($this->charset, ENT_QUOTES),
        );
    }
}
