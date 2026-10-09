<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Meta\Tag;

use Lemonade\Framework\Component\Meta\Traits\SimpleTagTrait;

/**
 * Renders an escaped document title when a title is available.
 */
final readonly class TitleTag implements TagInterface
{
    use SimpleTagTrait;

    /**
     * Initializes the optional title content.
     */
    public function __construct(
        private ?string $title,
    ) {
    }

    /**
     * Renders the title element or suppresses it for empty content.
     */
    public function render(): string
    {
        return $this->renderSimpleTag(
            '<title>%s</title>',
            $this->title,
        );
    }
}
