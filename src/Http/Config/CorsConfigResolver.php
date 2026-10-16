<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Config;

use Lemonade\Framework\Core\Config\Internal\ConfigValueNormalizer;

/**
 * Merges ordered CORS definition layers into one normalized runtime policy.
 */
final readonly class CorsConfigResolver
{
    /**
     * Resolves later definitions over earlier values while retaining framework defaults.
     */
    public function resolve(CorsConfigDefinition ...$definitions): CorsConfig
    {
        $enabled = false;
        $allowedOrigins = [];
        $allowedMethods = [];
        $allowedHeaders = [];
        $exposedHeaders = [];
        $allowCredentials = false;
        $maxAge = null;

        foreach ($definitions as $definition) {
            $data = $definition->toArray();

            if (array_key_exists('enabled', $data)) {
                $enabled = ConfigValueNormalizer::boolOr($data['enabled'], $enabled);
            }

            if (array_key_exists('allowed_origins', $data)) {
                $allowedOrigins = ConfigValueNormalizer::stringList($data['allowed_origins']);
            }

            if (array_key_exists('allowed_methods', $data)) {
                $allowedMethods = ConfigValueNormalizer::stringList($data['allowed_methods']);
            }

            if (array_key_exists('allowed_headers', $data)) {
                $allowedHeaders = ConfigValueNormalizer::stringList($data['allowed_headers']);
            }

            if (array_key_exists('exposed_headers', $data)) {
                $exposedHeaders = ConfigValueNormalizer::stringList($data['exposed_headers']);
            }

            if (array_key_exists('allow_credentials', $data)) {
                $allowCredentials = ConfigValueNormalizer::boolOr(
                    $data['allow_credentials'],
                    $allowCredentials,
                );
            }

            if (array_key_exists('max_age', $data)) {
                $maxAge = ConfigValueNormalizer::nullableInt($data['max_age']);
            }
        }

        return new CorsConfig(
            enabled: $enabled,
            allowedOrigins: $allowedOrigins,
            allowedMethods: $allowedMethods,
            allowedHeaders: $allowedHeaders,
            exposedHeaders: $exposedHeaders,
            allowCredentials: $allowCredentials,
            maxAge: $maxAge,
        );
    }
}
