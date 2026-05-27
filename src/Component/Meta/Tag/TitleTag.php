<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Meta\Tag;

use Lemonade\Framework\Component\Meta\Traits\SimpleTagTrait;

final class TitleTag implements TagInterface
{
    use SimpleTagTrait;

    public function __construct(
        private readonly ?string $title,
    ) {}

    public function render(): string
    {
        return $this->renderSimpleTag(
            '<title>%s</title>',
            $this->title,
        );
    }
}
