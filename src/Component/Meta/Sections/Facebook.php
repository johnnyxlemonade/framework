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
        $tags[] = new OpenGraphTag('og:url', $this->data->getUrl() ?? $this->data->getCanonical());
        $tags[] = new OpenGraphTag('og:image', $this->data->getImage());
        $tags[] = new OpenGraphTag('og:image:alt', $this->imageAlt());
        $tags[] = new OpenGraphTag('og:locale', $this->data->getLocale() ?? ($custom['og:locale'] ?? null));
        $tags[] = new OpenGraphTag('og:type', $this->data->getType() ?? ($custom['og:type'] ?? 'website'));

        // Přidání Facebook App ID, pokud je nastaveno
        if (isset($custom['fb:app_id']) && $custom['fb:app_id'] !== '') {
            $tags[] = new OpenGraphTag('fb:app_id', $custom['fb:app_id']);
        }

        // Dynamické přidání dalších custom tagů, pokud existují
        foreach ($custom as $key => $value) {
            // Předpokládáme, že všechny custom tagy jsou OG tagy
            if (
                str_starts_with($key, 'og:')
                && !in_array($key, ['og:url', 'og:type', 'og:locale', 'og:image:alt'], true)
                && $value !== null
                && $value !== ''
            ) {
                $tags[] = new OpenGraphTag($key, $value);
            }
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
