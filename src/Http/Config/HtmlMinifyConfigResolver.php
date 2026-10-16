<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Config;

use Lemonade\Framework\Core\Config\Internal\ConfigValueNormalizer;

/**
 * Merges ordered HTML minification definitions into one runtime setting.
 */
final readonly class HtmlMinifyConfigResolver
{
    /**
     * Resolves the last configured switch, defaulting to disabled.
     */
    public function resolve(HtmlMinifyConfigDefinition ...$definitions): HtmlMinifyConfig
    {
        $enabled = false;

        foreach ($definitions as $definition) {
            $data = $definition->toArray();

            if (array_key_exists('enabled', $data)) {
                $enabled = ConfigValueNormalizer::boolOr($data['enabled'], false);
            }
        }

        return new HtmlMinifyConfig($enabled);
    }
}
