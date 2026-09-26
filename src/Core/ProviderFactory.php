<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core;

use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Container\Exception\ScopedServiceRequestedFromRootException;
use Lemonade\Framework\Container\ScopedContainerInterface;
use Lemonade\Framework\Container\ScopeFactoryInterface;
use Lemonade\Framework\Core\Exception\ProviderConstructionException;
use Psr\Container\ContainerInterface as PsrContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use Throwable;

/**
 * Creates configured providers from services that are safe during bootstrap.
 *
 * This deliberately does not use the container's concrete-class autowiring
 * fallback. A provider constructor may receive only an explicitly bound root
 * service that existed before configured providers begin registration.
 */
final class ProviderFactory
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    /**
     * @param class-string $providerClass
     */
    public function create(string $providerClass): object
    {
        $reflection = new ReflectionClass($providerClass);

        if (!$reflection->isInstantiable()) {
            throw new ProviderConstructionException(sprintf(
                'Cannot construct service provider "%s": the class is not instantiable.',
                $providerClass,
            ));
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return $reflection->newInstance();
        }

        $arguments = [];

        foreach ($constructor->getParameters() as $parameter) {
            $arguments[] = $this->argument($providerClass, $parameter);
        }

        return $reflection->newInstanceArgs($arguments);
    }

    private function argument(string $providerClass, ReflectionParameter $parameter): mixed
    {
        $type = $parameter->getType();

        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return $this->defaultArgumentOrFail($providerClass, $parameter);
        }

        $dependency = $type->getName();

        if ($this->isRuntimeOnlyDependency($dependency)) {
            throw new ProviderConstructionException(sprintf(
                'Cannot construct service provider "%s": constructor dependency "%s" for $%s is runtime-only. Provider constructors cannot receive the container, scopes, or request values.',
                $providerClass,
                $dependency,
                $parameter->getName(),
            ));
        }

        if (!$this->container->isBound($dependency)) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            throw new ProviderConstructionException(sprintf(
                'Cannot construct service provider "%s": constructor dependency "%s" for $%s is not an explicitly bound bootstrap service.',
                $providerClass,
                $dependency,
                $parameter->getName(),
            ));
        }

        try {
            return $this->container->get($dependency);
        } catch (ScopedServiceRequestedFromRootException $exception) {
            throw new ProviderConstructionException(sprintf(
                'Cannot construct service provider "%s": constructor dependency "%s" for $%s is scope-local and unavailable during bootstrap.',
                $providerClass,
                $dependency,
                $parameter->getName(),
            ), previous: $exception);
        } catch (Throwable $exception) {
            throw new ProviderConstructionException(sprintf(
                'Cannot construct service provider "%s": bootstrap service "%s" for $%s could not be resolved.',
                $providerClass,
                $dependency,
                $parameter->getName(),
            ), previous: $exception);
        }
    }

    private function defaultArgumentOrFail(string $providerClass, ReflectionParameter $parameter): mixed
    {
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        throw new ProviderConstructionException(sprintf(
            'Cannot construct service provider "%s": constructor parameter $%s must be an explicitly bound bootstrap service or declare a default value.',
            $providerClass,
            $parameter->getName(),
        ));
    }

    private function isRuntimeOnlyDependency(string $dependency): bool
    {
        return in_array($dependency, [
            ContainerInterface::class,
            ContainerBuilderInterface::class,
            PsrContainerInterface::class,
            ScopedContainerInterface::class,
            ScopeFactoryInterface::class,
            ServerRequestInterface::class,
        ], true);
    }
}
