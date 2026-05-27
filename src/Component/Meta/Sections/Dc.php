<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Meta\Sections;

use Lemonade\Framework\Component\Meta\Tag\DcTag;

final class Dc extends AbstractMetaEntity
{
    public function render(): string
    {
        $tags = [];

        // DC metatags
        $tags[] = new DcTag('title', $this->data->getTitle());
        $tags[] = new DcTag('description', $this->data->getDescription());
        $tags[] = new DcTag('keywords', $this->data->getKeywords());
        $tags[] = new DcTag('creator', $this->data->getAuthor());
        $tags[] = new DcTag('publisher', $this->data->getAuthor());
        $tags[] = new DcTag('rights', $this->data->getAuthor());

        return $this->renderTags($tags);
    }
}
