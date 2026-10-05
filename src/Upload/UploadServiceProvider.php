<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload;

use Lemonade\Framework\Cli\CommandDefinition;
use Lemonade\Framework\Cli\CommandRegistry;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\ProviderContainerAssertions;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Upload\Chunk\ChunkUploadCleanupCommand;
use Lemonade\Framework\Upload\Chunk\ChunkUploadConfig;
use Lemonade\Framework\Upload\Chunk\ChunkUploadService;
use Lemonade\Framework\Upload\Chunk\FilesystemChunkUploadSessionStore;
use Lemonade\Framework\Upload\Config\UploadConfig;
use Lemonade\Framework\Upload\Config\UploadConfigDefinition;
use Lemonade\Framework\Upload\Config\UploadConfigResolver;
use Lemonade\Framework\Upload\Storage\UploadStorage;

/**
 * Registers the request-scoped upload API, its shared validation dependencies, and chunk-upload infrastructure.
 */
final readonly class UploadServiceProvider implements ServiceProviderInterface
{
    /**
     * Registers classic and chunk upload services while preserving request scope for APIs that depend on UploadFactory.
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
}
