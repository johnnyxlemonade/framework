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

    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    public function register(object ...$providers): void
    {
        foreach ($providers as $provider) {
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
                if (!$this->container instanceof ContainerBuilderInterface) {
                    throw new LogicException(sprintf(
                        'Definition service provider "%s" requires a container implementing %s.',
                        $provider::class,
                        ContainerBuilderInterface::class,
                    ));
                }

                $provider->register($this->container);
            } elseif ($provider instanceof ServiceProviderInterface) {
                $provider->register($this->container);
            }

            if ($provider instanceof BootableServiceProviderInterface) {
                $this->bootableProviders[] = $provider;
            }
        }
    }

    /**
     * Compiles the currently registered definitions before booting newly added providers.
     */
    public function boot(): void
    {
        if ($this->container instanceof ContainerBuilderInterface) {
            $this->container->compile();
        }

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
