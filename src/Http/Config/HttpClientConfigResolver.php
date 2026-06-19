<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Config;

final class HttpClientConfigResolver
{
    public function resolve(HttpClientConfigDefinition ...$definitions): HttpClientConfig
    {
        $timeout = 10.0;
        $connectTimeout = 5.0;
        $verifySsl = true;

        foreach ($definitions as $definition) {
            $data = $definition->toArray();

            if (array_key_exists('timeout', $data)) {
                $timeout = $this->floatOr($data['timeout'], $timeout);
            }

            if (array_key_exists('connect_timeout', $data)) {
                $connectTimeout = $this->floatOr($data['connect_timeout'], $connectTimeout);
            }

            if (array_key_exists('verify_ssl', $data)) {
                $verifySsl = $this->toBool($data['verify_ssl'], $verifySsl);
            }
        }

        return new HttpClientConfig($timeout, $connectTimeout, $verifySsl);
    }

    private function floatOr(mixed $value, float $default): float
    {
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return $default;
    }

    private function toBool(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (!is_scalar($value)) {
            return $default;
        }

        $resolved = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $resolved ?? $default;
    }
}
