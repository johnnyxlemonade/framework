<?php

declare(strict_types=1);

namespace Lemonade\Framework\Observability\Http\Config;

use Lemonade\Framework\Core\Config\Internal\ConfigValueNormalizer;

/**
 * Resolves ordered diagnostic exposure settings without consulting environment or debug state.
 */
final readonly class HttpDiagnosticsConfigResolver
{
    /**
     * Defaults to disabled and applies explicit overrides; invalid values disable exposure.
     */
    public function resolve(HttpDiagnosticsConfigDefinition ...$definitions): HttpDiagnosticsConfig
    {
        $enabled = false;

        foreach ($definitions as $definition) {
            $data = $definition->toArray();

            if (array_key_exists('enabled', $data)) {
                $enabled = ConfigValueNormalizer::boolOr($data['enabled'], false);
            }
        }

        return new HttpDiagnosticsConfig($enabled);
    }
}
