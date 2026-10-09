<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Meta\Sections;

use Lemonade\Framework\Component\Meta\MetaData;
use Lemonade\Framework\Component\Meta\Tag\TagInterface;

/**
 * Shares immutable page metadata and tag-list rendering among metadata sections.
 *
 * Custom subclasses must remain readonly to preserve their fixed page metadata.
 */
abstract readonly class AbstractMetaEntity implements MetaEntityInterface
{
    /**
     * Initializes a section with the metadata it will render.
     */
    public function __construct(
        protected MetaData $data,
    ) {
    }

    /**
     * Renders only non-empty tags, separating each rendered tag by a newline.
     *
     * @param TagInterface[] $tags
     */
    protected function renderTags(array $tags): string
    {
        $html = array_map(fn(TagInterface $tag) => $tag->render(), $tags);
        $html = array_filter($html, fn(string $tag) => $tag !== '');

        return implode(PHP_EOL, $html) . PHP_EOL;
    }
}
