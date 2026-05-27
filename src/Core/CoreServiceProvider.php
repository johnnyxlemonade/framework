<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core;

use Lemonade\Framework\Adapter\LoaderAdapter;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Diagnostics\ExceptionLogger;
use Lemonade\Framework\Http\Psr\ServerRequestFactory;
use Lemonade\Framework\Support\BaseUrlResolver;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;

final class CoreServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        /*
         * PSR-7 / PSR-17 factories.
         *
         * Nyholm's Psr17Factory implements all PSR-17 factory interfaces used by the framework.
         */
        $container->singleton(Psr17Factory::class, Psr17Factory::class);

        $container->singleton(ResponseFactoryInterface::class, Psr17Factory::class);
        $container->singleton(RequestFactoryInterface::class, Psr17Factory::class);
        $container->singleton(ServerRequestFactoryInterface::class, Psr17Factory::class);
        $container->singleton(StreamFactoryInterface::class, Psr17Factory::class);
        $container->singleton(UploadedFileFactoryInterface::class, Psr17Factory::class);
        $container->singleton(UriFactoryInterface::class, Psr17Factory::class);

        /*
         * Optional framework aliases for PSR factories.
         */
        $container->singleton('psr17', Psr17Factory::class);
        $container->singleton('http.responseFactory', ResponseFactoryInterface::class);
        $container->singleton('http.requestFactory', RequestFactoryInterface::class);
        $container->singleton('http.serverRequestFactory', ServerRequestFactoryInterface::class);
        $container->singleton('http.streamFactory', StreamFactoryInterface::class);
        $container->singleton('http.uploadedFileFactory', UploadedFileFactoryInterface::class);
        $container->singleton('http.uriFactory', UriFactoryInterface::class);

        /*
         * Framework server request factory.
         *
         * Creates ServerRequestInterface from PHP globals through Nyholm ServerRequestCreator.
         */
        $container->singleton(ServerRequestFactory::class, ServerRequestFactory::class);

        /*
         * Core framework utilities.
         */
        $container->singleton(ControllerResolver::class, ControllerResolver::class);
        $container->singleton(BaseUrlResolver::class, BaseUrlResolver::class);
        $container->singleton('baseUrl', BaseUrlResolver::class);
        $container->singleton(LoaderAdapter::class, LoaderAdapter::class);
        $container->singleton(FrameworkInfo::class, FrameworkInfo::class);
        $container->singleton(ExceptionLogger::class, ExceptionLogger::class);

    }
}
