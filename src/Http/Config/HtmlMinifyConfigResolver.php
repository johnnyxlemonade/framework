<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Config;

use Lemonade\Framework\Core\Config\Internal\ConfigValueNormalizer;

final class HtmlMinifyConfigResolver
{
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
