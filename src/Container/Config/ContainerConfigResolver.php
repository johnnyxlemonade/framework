<?php

declare(strict_types=1);

namespace Lemonade\Framework\Container\Config;

final class ContainerConfigResolver
{
    public function resolve(ContainerConfigDefinition ...$definitions): ContainerConfig
    {
        $autowireFallbackWarning = false;

        foreach ($definitions as $definition) {
            $data = $definition->toArray();

            if (array_key_exists('autowire_fallback_warning', $data)) {
                $autowireFallbackWarning = $this->toBool($data['autowire_fallback_warning'], $autowireFallbackWarning);
            }
        }

        return new ContainerConfig($autowireFallbackWarning);
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
