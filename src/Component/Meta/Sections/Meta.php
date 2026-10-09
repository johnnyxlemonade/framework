<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Meta\Sections;

use Lemonade\Framework\Component\Meta\Tag\AlternateLinkTag;
use Lemonade\Framework\Component\Meta\Tag\CharsetTag;
use Lemonade\Framework\Component\Meta\Tag\LinkTag;
use Lemonade\Framework\Component\Meta\Tag\MetaTag;
use Lemonade\Framework\Component\Meta\Tag\TitleTag;

/**
 * Renders standard document, SEO, canonical and alternate-language metadata.
 */
final readonly class Meta extends AbstractMetaEntity
{
    private const string GENERATOR = 'Lemonade CMS [lemonadeframework.cz]';

    /**
     * Renders standard tags while omitting custom values owned by social sections.
     */
    public function render(): string
    {
        $tags = [];

        // Base SEO tags.
        $tags[] = new CharsetTag($this->data->getCharset());
        $tags[] = new TitleTag($this->data->getTitle());
        $tags[] = new MetaTag('description', $this->data->getDescription());
        $tags[] = new MetaTag('keywords', $this->data->getKeywords());
        $tags[] = new MetaTag('author', $this->data->getAuthor());
        $tags[] = new MetaTag('viewport', $this->data->getViewport());
        $tags[] = new MetaTag('robots', $this->data->getRobots());

        // Framework/system tags.
        $tags[] = new MetaTag('generator', self::GENERATOR);
        $tags[] = new MetaTag('rating', $this->data->getRating());
        $tags[] = new MetaTag('web_author', $this->data->getAuthor());

        // Canonical URL supplied by the page owner.
        $tags[] = new LinkTag('canonical', $this->data->getCanonical());

        // Image link tag.
        $tags[] = new LinkTag('image_src', $this->data->getImage());

        // Alternate hreflang links.
        foreach ($this->data->getAlternates() as $lang => $url) {
            $tags[] = new AlternateLinkTag($lang, $url);
        }

        // Custom meta tags (key => value).
        foreach ($this->data->getCustom() as $key => $value) {
            if (
                str_starts_with($key, 'og:')
                || str_starts_with($key, 'fb:')
                || str_starts_with($key, 'twitter:')
            ) {
                continue;
            }

            $tags[] = new MetaTag($key, $value);
        }

        return $this->renderTags($tags);
    }
}
