<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core;

use Lemonade\Framework\Core\Exception\InvalidProviderDependencyException;
use Lemonade\Framework\Core\Exception\ProviderDependencyCycleException;
use Lemonade\Framework\Core\Exception\ProviderDependencyNotFoundException;

/** @internal Resolves stable provider registration order from explicit dependencies. */
final class ProviderDependencyResolver
{
    /**
     * @param list<object> $providers
     * @return list<object>
     */
    public function sort(array $providers): array
    {
        /** @var array<string, object> $providersByClass */
        $providersByClass = [];
        /** @var array<string, int> $positions */
        $positions = [];
        /** @var array<string, list<string>> $dependencies */
        $dependencies = [];

        foreach ($providers as $position => $provider) {
            $providerClass = $provider::class;

            if (isset($providersByClass[$providerClass])) {
                throw new InvalidProviderDependencyException(sprintf(
                    'Service provider "%s" is registered more than once in the same provider list.',
                    $providerClass,
                ));
            }

            $providersByClass[$providerClass] = $provider;
            $positions[$providerClass] = $position;
        }

        foreach ($providersByClass as $providerClass => $provider) {
            $dependencies[$providerClass] = $this->dependenciesFor($provider, $providersByClass);
        }

        /** @var array<string, int> $inDegree */
        $inDegree = [];
        /** @var array<string, list<string>> $dependents */
        $dependents = [];

        foreach (array_keys($providersByClass) as $providerClass) {
            $inDegree[$providerClass] = 0;
            $dependents[$providerClass] = [];
        }

        foreach ($dependencies as $providerClass => $requiredProviders) {
            $inDegree[$providerClass] = count($requiredProviders);

            foreach ($requiredProviders as $requiredProvider) {
                $dependents[$requiredProvider][] = $providerClass;
            }
        }

        /** @var list<string> $available */
        $available = [];
        foreach ($inDegree as $providerClass => $count) {
            if ($count === 0) {
                $available[] = $providerClass;
            }
        }

        $this->sortByConfigPosition($available, $positions);

        /** @var list<object> $sorted */
        $sorted = [];
        while ($available !== []) {
            $providerClass = array_shift($available);
            if ($providerClass === null) {
                break;
            }

            $sorted[] = $providersByClass[$providerClass];

            foreach ($dependents[$providerClass] as $dependent) {
                $inDegree[$dependent]--;
                if ($inDegree[$dependent] === 0) {
                    $available[] = $dependent;
                }
            }

            $this->sortByConfigPosition($available, $positions);
        }

        if (count($sorted) !== count($providers)) {
            $cycle = $this->findCycle($dependencies, $positions);

            throw new ProviderDependencyCycleException(sprintf(
                'Service provider dependency cycle detected: %s.',
                implode(' -> ', $cycle),
            ));
        }

        return $sorted;
    }

    /**
     * @param array<string, object> $providersByClass
     * @return list<string>
     */
    private function dependenciesFor(object $provider, array $providersByClass): array
    {
        if (!$provider instanceof DependentServiceProviderInterface) {
            return [];
        }

        $dependencies = [];
        foreach ($provider::requires() as $dependency) {
            if (!is_string($dependency) || trim($dependency) === '') {
                throw new InvalidProviderDependencyException(sprintf(
                    'Service provider "%s" declared an invalid dependency identifier.',
                    $provider::class,
                ));
            }

            if (!class_exists($dependency) || !ServiceProviderLifecycle::supports($dependency)) {
                throw new InvalidProviderDependencyException(sprintf(
                    'Service provider "%s" depends on "%s", which is not a supported provider class.',
                    $provider::class,
                    $dependency,
                ));
            }

            if (!isset($providersByClass[$dependency])) {
                throw new ProviderDependencyNotFoundException(sprintf(
                    'Service provider "%s" depends on "%s", but it is not present in the provider list.',
                    $provider::class,
                    $dependency,
                ));
            }

            if (!in_array($dependency, $dependencies, true)) {
                $dependencies[] = $dependency;
            }
        }

        return $dependencies;
    }

    /**
     * @param list<string> $providers
     * @param array<string, int> $positions
     */
    private function sortByConfigPosition(array &$providers, array $positions): void
    {
        usort(
            $providers,
            static fn(string $left, string $right): int => $positions[$left] <=> $positions[$right],
        );
    }

    /**
     * @param array<string, list<string>> $dependencies
     * @param array<string, int> $positions
     * @return list<string>
     */
    private function findCycle(array $dependencies, array $positions): array
    {
        /** @var array<string, 'visiting'|'visited'> $states */
        $states = [];
        /** @var list<string> $stack */
        $stack = [];
        /** @var list<string> $providers */
        $providers = array_keys($dependencies);
        $this->sortByConfigPosition($providers, $positions);

        foreach ($providers as $providerClass) {
            $cycle = $this->visitForCycle($providerClass, $dependencies, $states, $stack);
            if ($cycle !== null) {
                return $cycle;
            }
        }

        return [];
    }

    /**
     * @param array<string, list<string>> $dependencies
     * @param array<string, 'visiting'|'visited'> $states
     * @param list<string> $stack
     * @return list<string>|null
     */
    private function visitForCycle(string $providerClass, array $dependencies, array &$states, array &$stack): ?array
    {
        if (($states[$providerClass] ?? null) === 'visiting') {
            $start = array_search($providerClass, $stack, true);

            return [...array_slice($stack, $start === false ? 0 : $start), $providerClass];
        }

        if (($states[$providerClass] ?? null) === 'visited') {
            return null;
        }

        $states[$providerClass] = 'visiting';
        $stack[] = $providerClass;

        foreach ($dependencies[$providerClass] as $dependency) {
            $cycle = $this->visitForCycle($dependency, $dependencies, $states, $stack);
            if ($cycle !== null) {
                return $cycle;
            }
        }

        array_pop($stack);
        $states[$providerClass] = 'visited';

        return null;
    }
}
