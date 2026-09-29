<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload;

use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\ProviderContainerAssertions;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Upload\Config\UploadConfig;
use Lemonade\Framework\Upload\Config\UploadConfigDefinition;
use Lemonade\Framework\Upload\Config\UploadConfigResolver;
use Lemonade\Framework\Upload\Storage\UploadStorage;

/**
 * Registers the request-scoped upload API and its shared validation dependencies.
 */
final readonly class UploadServiceProvider implements ServiceProviderInterface
{
    /**
     * Registers upload configuration, validation, storage, and public factory services
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $builder = ProviderContainerAssertions::builder($container, self::class, 'request-scoped services');

        $container->singleton(UploadConfigResolver::class, UploadConfigResolver::class);
        $container->singleton(UploadConfig::class, static function (ContainerInterface $container): UploadConfig {
            $resolver = $container->get(UploadConfigResolver::class);
            $registry = $container->get(ConfigDefinitionRegistry::class);
            $entries = $registry->typedEntriesFor(
                UploadConfigDefinition::moduleKey(),
                UploadConfigDefinition::class,
            );

            return $resolver->resolve(...$entries);
        });

        /*
         * Upload validation.
         */
        $container->singleton(FileUploadValidator::class, FileUploadValidator::class);
        $container->singleton(ImageUploadValidator::class, ImageUploadValidator::class);

        /*
         * Upload infrastructure.
         */
        $container->singleton(UploadStorage::class, UploadStorage::class);

        /*
         * Upload public API.
         */
        $container->singleton(UploadService::class, UploadService::class);
        $builder->scoped(UploadFactory::class, UploadFactory::class);
    }
}
