<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Upload\Image\GdImageProcessor;
use Lemonade\Framework\Upload\Mime\MimeTypeDetector;
use Lemonade\Framework\Upload\Storage\UploadStorage;

final class UploadServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
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
        $container->singleton(UploadFactory::class, UploadFactory::class);
    }
}
