<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Meta\Tag;

use Lemonade\Framework\Component\Meta\Traits\HtmlAttributeTrait;

/**
 * Provides immutable attribute-based metadata tags while escaping their name and content.
 *
 * Custom subclasses must remain readonly to preserve the tag's fixed key and content.
 */
abstract readonly class AbstractTag implements TagInterface
{
    use HtmlAttributeTrait;

    /**
     * Initializes the tag's attribute value and optional content.
     */
    public function __construct(
        protected string $key,
        protected ?string $content,
    ) {
    }

    abstract protected function template(): string;

    /**
     * Renders the concrete tag template or suppresses it for empty content.
     */
    public function render(): string
    {
        return $this->renderTagWithAttribute(
            $this->template(),
            $this->key,
            $this->content,
        );
    }
}
