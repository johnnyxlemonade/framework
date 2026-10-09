<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Meta\Sections;

use Lemonade\Framework\Component\Meta\Tag\TwitterTag;

/**
 * Renders Twitter Card metadata using page defaults and supported custom overrides.
 */
final readonly class Twitter extends AbstractMetaEntity
{
    /**
     * Renders the Twitter Card tag set and an optional creator declaration.
     */
    public function render(): string
    {
        $tags = [];
        $custom = $this->data->getCustom();

        // základní Twitter Card
        $tags[] = new TwitterTag('twitter:card', $custom['twitter:card'] ?? 'summary');
        $tags[] = new TwitterTag('twitter:title', $this->data->getTitle());
        $tags[] = new TwitterTag('twitter:description', $this->data->getDescription());
        $tags[] = new TwitterTag('twitter:image', $this->data->getImage());
        $tags[] = new TwitterTag('twitter:image:alt', $this->imageAlt());

        // pokud máme autora / handle
        if (isset($custom['twitter:creator']) && $custom['twitter:creator'] !== '') {
            $tags[] = new TwitterTag('twitter:creator', $custom['twitter:creator']);
        }

        return $this->renderTags($tags);
    }

    private function imageAlt(): ?string
    {
        if ($this->data->getImage() === null || $this->data->getImage() === '') {
            return null;
        }

        return $this->data->getImageAlt();
    }
}
