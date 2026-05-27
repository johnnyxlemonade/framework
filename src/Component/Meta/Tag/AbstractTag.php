<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Meta\Tag;

use Lemonade\Framework\Component\Meta\Traits\HtmlAttributeTrait;

abstract class AbstractTag implements TagInterface
{
    use HtmlAttributeTrait;

    public function __construct(
        protected string $key,
        protected ?string $content,
    ) {}

    abstract protected function template(): string;

    public function render(): string
    {
        return $this->renderTagWithAttribute(
            $this->template(),
            $this->key,
            $this->content,
        );
    }
}
