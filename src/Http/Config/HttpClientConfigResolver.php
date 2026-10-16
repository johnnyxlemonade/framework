<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Config;

use Lemonade\Framework\Core\Config\Internal\ConfigValueNormalizer;

/**
 * Merges ordered HTTP client definitions into one normalized transport policy.
 */
final readonly class HttpClientConfigResolver
{
    /**
     * Resolves later values over earlier settings and framework client defaults.
     */
    public function resolve(HttpClientConfigDefinition ...$definitions): HttpClientConfig
    {
        $timeout = 10.0;
        $connectTimeout = 5.0;
        $verifySsl = true;

        foreach ($definitions as $definition) {
            $data = $definition->toArray();

            if (array_key_exists('timeout', $data)) {
                $timeout = ConfigValueNormalizer::floatOr($data['timeout'], $timeout);
            }

            if (array_key_exists('connect_timeout', $data)) {
                $connectTimeout = ConfigValueNormalizer::floatOr($data['connect_timeout'], $connectTimeout);
            }

            if (array_key_exists('verify_ssl', $data)) {
                $verifySsl = ConfigValueNormalizer::boolOr($data['verify_ssl'], $verifySsl);
            }
        }

        return new HttpClientConfig($timeout, $connectTimeout, $verifySsl);
    }
}
