<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload;

use Lemonade\Framework\Cli\CommandDefinition;
use Lemonade\Framework\Cli\CommandRegistry;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\BootableServiceProviderInterface;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\ProviderContainerAssertions;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Mime\MimeTypeCatalog;
use Lemonade\Framework\Upload\Chunk\ChunkUploadCleanupCommand;
use Lemonade\Framework\Upload\Chunk\ChunkUploadConfig;
use Lemonade\Framework\Upload\Chunk\ChunkUploadService;
use Lemonade\Framework\Upload\Chunk\FilesystemChunkUploadSessionStore;
use Lemonade\Framework\Upload\Config\UploadConfig;
use Lemonade\Framework\Upload\Config\UploadConfigDefinition;
use Lemonade\Framework\Upload\Config\UploadConfigResolver;
use Lemonade\Framework\Upload\Storage\UploadStorage;

/**
 * Registers upload validation, profile resolution, storage, and request-scoped upload entry points.
 *
 * Profile configuration is resolved during provider boot so invalid extension policies and byte limits fail before
 * requests are handled.
 */
final readonly class UploadServiceProvider implements ServiceProviderInterface, BootableServiceProviderInterface
{
    /**
     * Registers shared upload services and keeps request-bound factories scoped to the active request.
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $builder = ProviderContainerAssertions::builder($container, self::class, 'request-scoped services');

        $container->singleton(
            UploadConfigResolver::class,
            static function (ContainerInterface $container): UploadConfigResolver {
                if (
                    $container instanceof ContainerBuilderInterface
                    && $container->isBound(MimeTypeCatalog::class)
                ) {
                    $catalog = $container->get(MimeTypeCatalog::class);
                } else {
                    $catalog = MimeTypeCatalog::default();
                }

                return new UploadConfigResolver($catalog);
            },
        );
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
        $container->singleton(ChunkUploadConfig::class, ChunkUploadConfig::class);
        $container->singleton(FilesystemChunkUploadSessionStore::class, FilesystemChunkUploadSessionStore::class);
        $container->set(ChunkUploadCleanupCommand::class, ChunkUploadCleanupCommand::class);

        /*
         * Upload public API.
         */
        $container->singleton(UploadService::class, UploadService::class);
        $builder->scoped(UploadFactory::class, UploadFactory::class);
        $builder->scoped(ChunkUploadService::class, ChunkUploadService::class);

        if ($container->isBound(CommandRegistry::class)) {
            $container->get(CommandRegistry::class)->registerDefinition(new CommandDefinition(
                name: 'upload:chunks:cleanup',
                commandClass: ChunkUploadCleanupCommand::class,
                description: 'Remove expired temporary chunk uploads.',
            ));
        }
    }

    /**
     * Materializes the resolved profile set once provider composition is frozen.
     *
     * @throws \InvalidArgumentException When application upload configuration cannot form a safe policy
     */
    public function boot(ContainerInterface $container): void
    {
        $container->get(UploadConfig::class);
    }
}
