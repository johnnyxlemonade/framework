<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Logging\Config;

use Lemonade\Framework\Core\Config\Internal\ConfigValueNormalizer;

/**
 * Resolves layered logging definitions into the framework's minimal built-in logging policy.
 */
final class LoggingConfigResolver
{
    /**
     * Resolves layered logging definitions while retaining only canonical policy keys.
     */
    public function resolve(LoggingConfigDefinition ...$definitions): LoggingConfig
    {
        $retentionDays = 7;
        $requestEnabled = false;
        $requestMinStatus = 0;

        foreach ($definitions as $definition) {
            $data = $definition->toArray();
            $request = ConfigValueNormalizer::assoc($data['request'] ?? null);

            if (array_key_exists('retention_days', $data)) {
                $retentionDays = max(1, ConfigValueNormalizer::intOr($data['retention_days'], $retentionDays));
            }

            if (array_key_exists('enabled', $request)) {
                $requestEnabled = ConfigValueNormalizer::boolOr($request['enabled'], $requestEnabled);
            }

            if (array_key_exists('min_status', $request)) {
                $requestMinStatus = max(0, ConfigValueNormalizer::intOr($request['min_status'], $requestMinStatus));
            }
        }

        return new LoggingConfig(
            retentionDays: $retentionDays,
            requestEnabled: $requestEnabled,
            requestMinStatus: $requestMinStatus,
        );
    }
}
