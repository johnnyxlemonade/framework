<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core;

use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use LogicException;

/** @internal Coordinates definition registration, legacy registration and runtime booting. */
final class ServiceProviderLifecycle
{
    /** @var list<BootableServiceProviderInterface> */
    private array $bootableProviders = [];

    private int $bootedProviders = 0;

    private readonly ContainerBuilderInterface $builder;

    public function __construct(
        private readonly ContainerInterface $container,
    ) {
        if (!$container instanceof ContainerBuilderInterface) {
            throw new LogicException(sprintf(
                'Provider registration requires a container implementing %s.',
                ContainerBuilderInterface::class,
            ));
        }

        $this->builder = $container;
    }

    public function register(object ...$providers): void
    {
        $this->registerPlan(new ProviderLifecyclePlan(array_values($providers)));
    }

    public function registerPlan(ProviderLifecyclePlan $plan): void
    {
        foreach ($plan->providers() as $provider) {
            if (!self::supports($provider)) {
                throw new LogicException(sprintf(
                    'Service provider "%s" must implement %s, %s or %s.',
                    $provider::class,
                    ServiceProviderInterface::class,
                    DefinitionServiceProviderInterface::class,
                    BootableServiceProviderInterface::class,
                ));
            }

            if ($provider instanceof DefinitionServiceProviderInterface) {
                $provider->register($this->builder);
            } elseif ($provider instanceof ServiceProviderInterface) {
                $provider->register($this->builder);
            }

            if ($provider instanceof BootableServiceProviderInterface) {
                $this->bootableProviders[] = $provider;
            }
        }
    }

    /**
     * Freezes registered definitions before booting runtime providers.
     */
    public function boot(): void
    {
        $this->builder->freeze();

        $count = count($this->bootableProviders);

        for ($index = $this->bootedProviders; $index < $count; $index++) {
            $this->bootableProviders[$index]->boot($this->container);
        }

        $this->bootedProviders = $count;
    }

    public static function supports(object|string $provider): bool
    {
        return is_a($provider, ServiceProviderInterface::class, true)
            || is_a($provider, DefinitionServiceProviderInterface::class, true)
            || is_a($provider, BootableServiceProviderInterface::class, true);
    }
}
