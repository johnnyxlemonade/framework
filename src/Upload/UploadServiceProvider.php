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

/**
 * Registers upload validation, risk and path policy, storage, and request-scoped upload entry points.
 *
 * Profile configuration is resolved during provider boot so unsafe paths, storage overlap, unauthorized format risks,
 * and invalid byte limits fail before requests are handled.
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

        $container->singleton(ChunkUploadConfig::class, ChunkUploadConfig::class);
        $container->singleton(FilesystemChunkUploadSessionStore::class, FilesystemChunkUploadSessionStore::class);
        $container->set(ChunkUploadCleanupCommand::class, ChunkUploadCleanupCommand::class);

        $container->singleton(UploadService::class, UploadService::class);
        $builder->scoped(UploadFactory::class, UploadFactory::class);
        $builder->scoped(ChunkUploadService::class, ChunkUploadService::class);

        if ($container->isBound(CommandRegistry::class)) {
            $container->get(CommandRegistry::class)->registerDefinition(new CommandDefinition(
                name: 'upload:chunks:cleanup',
                commandClass: ChunkUploadCleanupCommand::class,
                description: 'Remove expired chunk sessions and orphaned upload staging files.',
            ));
        }
    }

    /**
     * Materializes profiles and rejects configured private storage that overlaps the public web root.
     *
     * @throws \InvalidArgumentException When application upload configuration cannot form a safe policy
     * @throws \LogicException When configured private storage overlaps the public web root
     */
    public function boot(ContainerInterface $container): void
    {
        $pathPolicy = $container->get(UploadPathPolicy::class);
        $config = $container->get(UploadConfig::class);

        foreach ([...$config->files, ...$config->images] as $profile) {
            if ($profile->visibility() !== UploadVisibility::Private) {
                continue;
            }

            $pathPolicy->assertStorageRootsSeparated();

            return;
        }
    }
}
