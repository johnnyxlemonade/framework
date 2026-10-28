<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http;

use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\Http\RequestData;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Http\Bags\ServerBag;
use Lemonade\Framework\Http\Config\CorsConfig;
use Lemonade\Framework\Http\Config\CorsConfigDefinition;
use Lemonade\Framework\Http\Config\CorsConfigResolver;
use Lemonade\Framework\Http\Config\ErrorConfig;
use Lemonade\Framework\Http\Config\ErrorConfigDefinition;
use Lemonade\Framework\Http\Config\ErrorConfigResolver;
use Lemonade\Framework\Http\Config\HtmlMinifyConfig;
use Lemonade\Framework\Http\Config\HtmlMinifyConfigDefinition;
use Lemonade\Framework\Http\Config\HtmlMinifyConfigResolver;
use Lemonade\Framework\Http\Middleware\CorsMiddleware;
use Lemonade\Framework\Http\Middleware\DispatchRequestHandler;
use Lemonade\Framework\Http\Middleware\ErrorHandlingMiddleware;
use Lemonade\Framework\Http\Middleware\HtmlMinifyMiddleware;
use Lemonade\Framework\Http\Middleware\MiddlewareResolver;
use Lemonade\Framework\Http\Middleware\MiddlewareStack;
use Lemonade\Framework\Http\Middleware\OptionsMiddleware;
use Lemonade\Framework\Http\Middleware\PoweredByMiddleware;
use Lemonade\Framework\Http\Request\HttpRequestInspector;
use Lemonade\Framework\Http\Request\ServerHelper;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Registers the framework HTTP pipeline, request-scoped dispatch services, and resolved HTTP configuration.
 */
final readonly class HttpServiceProvider implements ServiceProviderInterface
{
    /**
     * Defines HTTP service lifetimes and the default global middleware order.
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $this->registerConfiguration($container);
        $this->registerMiddleware($container);
        $this->registerRequestServices($container);
    }

    /**
     * Registers lazy HTTP configuration snapshots while resolvers remain autowired concretes.
     */
    private function registerConfiguration(ContainerBuilderInterface $container): void
    {
        $container->singleton(HtmlMinifyConfig::class, static function (ContainerInterface $container): HtmlMinifyConfig {
            $resolver = $container->get(HtmlMinifyConfigResolver::class);
            $registry = $container->get(ConfigDefinitionRegistry::class);
            $definitions = $registry->typedEntriesFor(
                HtmlMinifyConfigDefinition::moduleKey(),
                HtmlMinifyConfigDefinition::class,
            );

            return $resolver->resolve(...$definitions);
        });

        $container->singleton(CorsConfig::class, static function (ContainerInterface $container): CorsConfig {
            $resolver = $container->get(CorsConfigResolver::class);
            $registry = $container->get(ConfigDefinitionRegistry::class);
            $definitions = $registry->typedEntriesFor(
                CorsConfigDefinition::moduleKey(),
                CorsConfigDefinition::class,
            );

            return $resolver->resolve(...$definitions);
        });

        $container->singleton(ErrorConfig::class, static function (ContainerInterface $container): ErrorConfig {
            $resolver = $container->get(ErrorConfigResolver::class);
            $registry = $container->get(ConfigDefinitionRegistry::class);
            $definitions = $registry->typedEntriesFor(
                ErrorConfigDefinition::moduleKey(),
                ErrorConfigDefinition::class,
            );

            return $resolver->resolve(...$definitions);
        });
    }

    /**
     * Registers the mutable global ordering policy while middleware remains autowired.
     */
    private function registerMiddleware(ContainerBuilderInterface $container): void
    {
        $container->singleton(MiddlewareStack::class, static fn(): MiddlewareStack => new MiddlewareStack([
            ErrorHandlingMiddleware::class,
            CorsMiddleware::class,
            PoweredByMiddleware::class,
            HtmlMinifyMiddleware::class,
            OptionsMiddleware::class,
        ]));
    }

    /**
     * Keeps request-bound dispatch and request data inside the active request scope.
     */
    private function registerRequestServices(ContainerBuilderInterface $container): void
    {
        $container->scoped(DispatchRequestHandler::class, DispatchRequestHandler::class);
        $container->scoped(MiddlewareResolver::class, MiddlewareResolver::class);
        $container->scoped(HttpRequestInspector::class, HttpRequestInspector::class);
        $container->scoped(ServerHelper::class, ServerHelper::class);
        $container->scoped(ServerBag::class, ServerBag::class);
        $container->scoped(
            RequestData::class,
            static function (ContainerInterface $container): RequestData {
                return new RequestData(
                    $container->get(ServerRequestInterface::class),
                    $container->get(HttpRequestInspector::class),
                );
            },
        );
    }
}
