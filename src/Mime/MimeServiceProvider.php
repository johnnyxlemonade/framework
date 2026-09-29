<?php

declare(strict_types=1);

namespace Lemonade\Framework\Mime;

use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Container\TaggedServicesInterface;
use Lemonade\Framework\Core\BootableServiceProviderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use LogicException;

/**
 * Composes immutable MIME knowledge after all framework and application providers register.
 */
final readonly class MimeServiceProvider implements ServiceProviderInterface, BootableServiceProviderInterface
{
    /**
     * Registers the catalog factory and server-side content detector before provider composition closes
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $container->singleton(
            MimeTypeCatalog::class,
            $this->createCatalog(...),
        );

        $container->singleton(MimeTypeDetector::class, MimeTypeDetector::class);
        $container->singleton(MimeTypeDetectorInterface::class, MimeTypeDetector::class);
    }

    /**
     * Materializes the catalog only after the frozen provider plan contains every tagged contribution
     */
    public function boot(ContainerInterface $container): void
    {
        $container->get(MimeTypeCatalog::class);
    }

    private function createCatalog(ContainerInterface $container): MimeTypeCatalog
    {
        if (!$container instanceof ContainerBuilderInterface || !$container->isFrozen()) {
            throw new LogicException('MIME catalog cannot be resolved before provider composition is frozen.');
        }

        if (!$container instanceof TaggedServicesInterface) {
            throw new LogicException(sprintf(
                'MIME catalog composition requires a container implementing %s.',
                TaggedServicesInterface::class,
            ));
        }

        $definitions = [];

        foreach ($container->tagged(MimeTypeDefinitionProviderInterface::class) as $serviceId => $provider) {
            if (!$provider instanceof MimeTypeDefinitionProviderInterface) {
                throw new LogicException(sprintf(
                    'Tagged MIME definition provider "%s" must implement %s.',
                    $serviceId,
                    MimeTypeDefinitionProviderInterface::class,
                ));
            }

            foreach ($provider->definitions() as $definition) {
                if (!$definition instanceof MimeTypeDefinition) {
                    throw new LogicException(sprintf(
                        'MIME definition provider "%s" must return only %s instances.',
                        $serviceId,
                        MimeTypeDefinition::class,
                    ));
                }

                $definitions[] = $definition;
            }
        }

        return MimeTypeCatalog::fromDefaultAndAdditionalDefinitions($definitions);
    }
}
