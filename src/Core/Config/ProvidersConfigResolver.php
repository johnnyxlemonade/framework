<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Config;

use Lemonade\Framework\Core\ServiceProviderLifecycle;
use LogicException;

final class ProvidersConfigResolver
{
    public function resolve(ProvidersConfigDefinition ...$definitions): ProvidersConfig
    {
        $providers = [];

        foreach ($definitions as $definition) {
            $providers = $this->resolveProviders($definition->toArray());
        }

        return new ProvidersConfig($providers);
    }

    /**
     * @param array<mixed> $value
     * @return list<class-string>
     */
    private function resolveProviders(array $value): array
    {
        $providers = [];

        foreach ($value as $providerClass) {
            if (!is_string($providerClass) || !class_exists($providerClass)) {
                throw new LogicException(sprintf(
                    'Configured service provider "%s" does not exist.',
                    is_scalar($providerClass) ? (string) $providerClass : get_debug_type($providerClass),
                ));
            }

            if (!ServiceProviderLifecycle::supports($providerClass)) {
                throw new LogicException(sprintf(
                    'Configured service provider "%s" must implement a supported provider interface.',
                    $providerClass,
                ));
            }

            /** @var class-string $providerClass */
            $providers[] = $providerClass;
        }

        return array_values(array_unique($providers));
    }
}
