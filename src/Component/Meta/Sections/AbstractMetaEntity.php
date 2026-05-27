<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Meta\Sections;

use Lemonade\Framework\Component\Meta\MetaData;
use Lemonade\Framework\Component\Meta\Tag\TagInterface;

abstract class AbstractMetaEntity implements MetaEntityInterface
{
    public function __construct(
        protected readonly MetaData $data,
    ) {}

    /**
     * @param TagInterface[] $tags
     */
    protected function renderTags(array $tags): string
    {
        $html = array_map(fn(TagInterface $tag) => $tag->render(), $tags);
        $html = array_filter($html, fn(string $tag) => $tag !== '');

        return implode(PHP_EOL, $html) . PHP_EOL;
    }
}
