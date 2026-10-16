<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Meta\Config;

use Lemonade\Framework\Core\Config\Internal\ConfigValueNormalizer;

/**
 * Merges ordered metadata definitions into defaults consumable by the metadata component.
 */
final class MetaConfigResolver
{
    /**
     * Resolves definitions in order, retaining a previous default for non-scalar configured values.
     */
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
                $websiteName = ConfigValueNormalizer::scalarStringOr($data['website_name'], $websiteName);
            }
            if (array_key_exists('charset', $data)) {
                $charset = ConfigValueNormalizer::scalarStringOr($data['charset'], $charset);
            }
            if (array_key_exists('viewport', $data)) {
                $viewport = ConfigValueNormalizer::scalarStringOr($data['viewport'], $viewport);
            }
            if (array_key_exists('rating', $data)) {
                $rating = ConfigValueNormalizer::scalarStringOr($data['rating'], $rating);
            }
            if (array_key_exists('title_separator', $data)) {
                $titleSeparator = ConfigValueNormalizer::scalarStringOr(
                    $data['title_separator'],
                    $titleSeparator,
                );
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

}
