<?php

declare(strict_types=1);

namespace Lemonade\Framework\Container\Config;

/**
 * Resolves layered container definitions into one runtime autowiring policy.
 */
final class ContainerConfigResolver
{
    /**
     * Applies definitions in registration order, retaining the last valid mode.
     */
    public function resolve(ContainerConfigDefinition ...$definitions): ContainerConfig
    {
        $autowire = AutowireMode::Permissive;

        foreach ($definitions as $definition) {
            $data = $definition->toArray();

            if (array_key_exists('autowire', $data)) {
                $autowire = $this->modeOr($data['autowire'], $autowire);
            }
        }

        return new ContainerConfig($autowire);
    }

    private function modeOr(mixed $value, AutowireMode $default): AutowireMode
    {
        if (!is_scalar($value)) {
            return $default;
        }

        $mode = strtolower(trim((string) $value));

        return AutowireMode::tryFrom($mode) ?? $default;
    }
}
