<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Meta\Sections;

use Lemonade\Framework\Component\Meta\Tag\TwitterTag;

final class Twitter extends AbstractMetaEntity
{
    public function render(): string
    {
        $tags = [];
        $custom = $this->data->getCustom();

        // základní Twitter Card
        $tags[] = new TwitterTag('twitter:card', $custom['twitter:card'] ?? 'summary');
        $tags[] = new TwitterTag('twitter:title', $this->data->getTitle());
        $tags[] = new TwitterTag('twitter:description', $this->data->getDescription());
        $tags[] = new TwitterTag('twitter:image', $this->data->getImage());

        // pokud máme autora / handle
        if (isset($custom['twitter:creator']) && $custom['twitter:creator'] !== '') {
            $tags[] = new TwitterTag('twitter:creator', $custom['twitter:creator']);
        }

        return $this->renderTags($tags);
    }
}
