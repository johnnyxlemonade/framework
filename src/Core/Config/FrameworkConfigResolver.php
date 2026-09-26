<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Config;

use Lemonade\Framework\Core\ServiceProviderLifecycle;
use LogicException;

final class FrameworkConfigResolver
{
    public function resolve(FrameworkConfigDefinition ...$definitions): FrameworkConfig
    {
        $providers = [];

        foreach ($definitions as $definition) {
            $data = $definition->toArray();

            if (!array_key_exists('providers', $data)) {
                continue;
            }

            $providers = $this->resolveProviders($data['providers']);
        }

        return new FrameworkConfig($providers);
    }

    /**
     * @return list<class-string>
     */
    private function resolveProviders(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $providers = [];

        foreach ($value as $providerClass) {
            if (!is_string($providerClass) || !class_exists($providerClass)) {
                throw new LogicException(sprintf(
                    'Configured framework service provider "%s" does not exist.',
                    is_scalar($providerClass) ? (string) $providerClass : get_debug_type($providerClass),
                ));
            }

            if (!ServiceProviderLifecycle::supports($providerClass)) {
                throw new LogicException(sprintf(
                    'Configured framework service provider "%s" must implement a supported provider interface.',
                    $providerClass,
                ));
            }

            /** @var class-string $providerClass */
            $providers[] = $providerClass;
        }

        return array_values(array_unique($providers));
    }
}
