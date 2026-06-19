<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Meta\Config;

final class MetaConfigResolver
{
    public function resolve(MetaConfigDefinition ...$definitions): MetaConfig
    {
        $websiteName = 'website';
        $charset = 'UTF-8';
        $viewport = 'width=device-width, initial-scale=1';
        $rating = 'General';
        $titleSeparator = ' - ';

        foreach ($definitions as $definition) {
            $data = $definition->toArray();

            if (array_key_exists('website_name', $data)) {
                $websiteName = $this->stringOr($data['website_name'], $websiteName);
            }
            if (array_key_exists('charset', $data)) {
                $charset = $this->stringOr($data['charset'], $charset);
            }
            if (array_key_exists('viewport', $data)) {
                $viewport = $this->stringOr($data['viewport'], $viewport);
            }
            if (array_key_exists('rating', $data)) {
                $rating = $this->stringOr($data['rating'], $rating);
            }
            if (array_key_exists('title_separator', $data)) {
                $titleSeparator = $this->stringOr($data['title_separator'], $titleSeparator);
            }
        }

        return new MetaConfig(
            websiteName: $websiteName,
            charset: $charset,
            viewport: $viewport,
            rating: $rating,
            titleSeparator: $titleSeparator,
        );
    }

    private function stringOr(mixed $value, string $default): string
    {
        if (!is_scalar($value)) {
            return $default;
        }

        return (string) $value;
    }
}
