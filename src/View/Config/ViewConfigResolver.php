<?php

declare(strict_types=1);

namespace Lemonade\Framework\View\Config;

use Lemonade\Framework\Core\Config\Internal\ConfigValueNormalizer;

final class ViewConfigResolver
{
    public function resolve(ViewConfigDefinition ...$definitions): ViewConfig
    {
        $basePath = 'app/Views';

        foreach ($definitions as $definition) {
            $data = $definition->toArray();

            if (array_key_exists('base_path', $data)) {
                $basePath = ConfigValueNormalizer::scalarStringOr($data['base_path'], $basePath);
            }
        }

        return new ViewConfig($basePath);
    }

}
