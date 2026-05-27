<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Meta\Sections;

use Lemonade\Framework\Component\Meta\Tag\OpenGraphTag;

final class Facebook extends AbstractMetaEntity
{
    public function render(): string
    {
        $tags = [];
        $custom = $this->data->getCustom();

        // základní OG tagy
        $tags[] = new OpenGraphTag('og:title', $this->data->getTitle());
        $tags[] = new OpenGraphTag('og:description', $this->data->getDescription());
        $tags[] = new OpenGraphTag('og:url', $this->data->getCanonicalUrl());
        $tags[] = new OpenGraphTag('og:image', $this->data->getImage());
        $tags[] = new OpenGraphTag('og:locale', $custom['og:locale'] ?? null);
        $tags[] = new OpenGraphTag('og:type', $custom['og:type'] ?? 'website');

        // Přidání Facebook App ID, pokud je nastaveno
        if (isset($custom['fb:app_id']) && $custom['fb:app_id'] !== '') {
            $tags[] = new OpenGraphTag('fb:app_id', $custom['fb:app_id']);
        }

        // Dynamické přidání dalších custom tagů, pokud existují
        foreach ($custom as $key => $value) {
            // Předpokládáme, že všechny custom tagy jsou OG tagy
            if (
                str_starts_with($key, 'og:')
                && $key !== 'og:locale'
                && $key !== 'og:type'
                && $value !== null
                && $value !== ''
            ) {
                $tags[] = new OpenGraphTag($key, $value);
            }
        }

        return $this->renderTags($tags);
    }
}
