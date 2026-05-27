<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Config;

use Lemonade\Framework\Core\Config;

final class ConfigDumper
{
    /**
     * @var array<int, string>
     */
    private array $sensitiveKeys = [
        'password',
        'pass',
        'secret',
        'token',
        'key',
        'api_key',
        'client_secret',
        'encryption_key',
    ];

    public function __construct(
        private readonly Config $config,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function all(bool $redactSensitive = true): array
    {
        $data = $this->config->all();

        if (!$redactSensitive) {
            return $data;
        }

        return $this->redact($data);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $nested = [];
                foreach ($value as $nestedKey => $nestedValue) {
                    if (is_string($nestedKey)) {
                        $nested[$nestedKey] = $nestedValue;
                    }
                }

                $data[$key] = $this->redact($nested);
                continue;
            }

            if ($this->isSensitiveKey($key)) {
                $data[$key] = '***';
            }
        }

        return $data;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);

        foreach ($this->sensitiveKeys as $sensitiveKey) {
            if (str_contains($normalized, $sensitiveKey)) {
                return true;
            }
        }

        return false;
    }
}
