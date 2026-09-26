<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload;

use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Upload\Config\UploadConfig;
use Lemonade\Framework\Upload\Config\UploadConfigDefinition;
use Lemonade\Framework\Upload\Config\UploadConfigResolver;
use Lemonade\Framework\Upload\Image\GdImageProcessor;
use Lemonade\Framework\Upload\Mime\MimeTypeDetector;
use Lemonade\Framework\Upload\Storage\UploadStorage;

final class UploadServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $builder = $this->builder($container);

        $container->singleton(UploadConfigResolver::class, UploadConfigResolver::class);
        $container->singleton(UploadConfig::class, static function (ContainerInterface $container): UploadConfig {
            return $container
                ->get(UploadConfigResolver::class)
                ->resolve(...$container->get(ConfigDefinitionRegistry::class)->typedEntriesFor(
                    UploadConfigDefinition::moduleKey(),
                    UploadConfigDefinition::class,
                ));
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
        $container->singleton(MimeTypeDetector::class, MimeTypeDetector::class);
        $container->singleton(GdImageProcessor::class, GdImageProcessor::class);

        /*
         * Upload public API.
         */
        $container->singleton(UploadService::class, UploadService::class);
        $builder->scoped(UploadFactory::class, UploadFactory::class);
    }

    private function builder(ContainerInterface $container): ContainerBuilderInterface
    {
        if (!$container instanceof ContainerBuilderInterface) {
            throw new \RuntimeException(sprintf(
                '%s requires a container implementing %s to register request-scoped services.',
                self::class,
                ContainerBuilderInterface::class,
            ));
        }

        return $container;
    }
}
