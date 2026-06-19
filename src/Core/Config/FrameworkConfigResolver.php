<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Config;

use Lemonade\Framework\Core\ServiceProviderInterface;
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
     * @return list<class-string<ServiceProviderInterface>>
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

            if (!is_subclass_of($providerClass, ServiceProviderInterface::class)) {
                throw new LogicException(sprintf(
                    'Configured framework service provider "%s" must implement %s.',
                    $providerClass,
                    ServiceProviderInterface::class,
                ));
            }

            /** @var class-string<ServiceProviderInterface> $providerClass */
            $providers[] = $providerClass;
        }

        return array_values(array_unique($providers));
    }
}
