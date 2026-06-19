<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http;

use Lemonade\Framework\Api\Config\ApiConfig;
use Lemonade\Framework\Api\Config\ApiConfigDefinition;
use Lemonade\Framework\Api\Config\ApiConfigResolver;
use Lemonade\Framework\Api\Http\Middleware\ApiAuthorizationMiddleware;
use Lemonade\Framework\Api\Security\ApiAuthenticatorInterface;
use Lemonade\Framework\Api\Security\NullApiAuthenticator;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Http\Config\CorsConfig;
use Lemonade\Framework\Http\Config\CorsConfigDefinition;
use Lemonade\Framework\Http\Config\CorsConfigResolver;
use Lemonade\Framework\Http\Config\ErrorConfig;
use Lemonade\Framework\Http\Config\ErrorConfigDefinition;
use Lemonade\Framework\Http\Config\ErrorConfigResolver;
use Lemonade\Framework\Http\Config\HtmlMinifyConfig;
use Lemonade\Framework\Http\Config\HtmlMinifyConfigDefinition;
use Lemonade\Framework\Http\Config\HtmlMinifyConfigResolver;
use Lemonade\Framework\Http\Error\ErrorPageRenderer;
use Lemonade\Framework\Http\Logging\HttpLogContext;
use Lemonade\Framework\Http\Middleware\BenchmarkMiddleware;
use Lemonade\Framework\Http\Middleware\CorsMiddleware;
use Lemonade\Framework\Http\Middleware\DispatchRequestHandler;
use Lemonade\Framework\Http\Middleware\ErrorHandlingMiddleware;
use Lemonade\Framework\Http\Middleware\HtmlMinifyMiddleware;
use Lemonade\Framework\Http\Middleware\MiddlewareResolver;
use Lemonade\Framework\Http\Middleware\MiddlewareStack;
use Lemonade\Framework\Http\Middleware\OptionsMiddleware;
use Lemonade\Framework\Http\Middleware\PoweredByMiddleware;
use Lemonade\Framework\Http\Middleware\RequestLoggingMiddleware;
use Lemonade\Framework\Http\Request\HttpRequestInspector;
use Lemonade\Framework\Http\Response\HtmlMinifier;

final class HttpServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(ErrorPageRenderer::class, ErrorPageRenderer::class);
        $container->singleton(ApiAuthenticatorInterface::class, static fn(): ApiAuthenticatorInterface => new NullApiAuthenticator());
        $container->singleton(ApiConfigResolver::class, ApiConfigResolver::class);
        $container->singleton(ApiConfig::class, static function (ContainerInterface $container): ApiConfig {
            return $container
                ->get(ApiConfigResolver::class)
                ->resolve(...$container->get(ConfigDefinitionRegistry::class)->typedEntriesFor(
                    ApiConfigDefinition::moduleKey(),
                    ApiConfigDefinition::class,
                ));
        });
        $container->singleton(HtmlMinifyConfigResolver::class, HtmlMinifyConfigResolver::class);
        $container->singleton(HtmlMinifyConfig::class, static function (ContainerInterface $container): HtmlMinifyConfig {
            return $container
                ->get(HtmlMinifyConfigResolver::class)
                ->resolve(...$container->get(ConfigDefinitionRegistry::class)->typedEntriesFor(
                    HtmlMinifyConfigDefinition::moduleKey(),
                    HtmlMinifyConfigDefinition::class,
                ));
        });
        $container->singleton(CorsConfigResolver::class, CorsConfigResolver::class);
        $container->singleton(CorsConfig::class, static function (ContainerInterface $container): CorsConfig {
            return $container
                ->get(CorsConfigResolver::class)
                ->resolve(...$container->get(ConfigDefinitionRegistry::class)->typedEntriesFor(
                    CorsConfigDefinition::moduleKey(),
                    CorsConfigDefinition::class,
                ));
        });
        $container->singleton(ErrorConfigResolver::class, ErrorConfigResolver::class);
        $container->singleton(ErrorConfig::class, static function (ContainerInterface $container): ErrorConfig {
            return $container
                ->get(ErrorConfigResolver::class)
                ->resolve(...$container->get(ConfigDefinitionRegistry::class)->typedEntriesFor(
                    ErrorConfigDefinition::moduleKey(),
                    ErrorConfigDefinition::class,
                ));
        });

        $container->singleton(ErrorHandlingMiddleware::class, ErrorHandlingMiddleware::class);
        $container->singleton(PoweredByMiddleware::class, PoweredByMiddleware::class);
        $container->singleton(RequestLoggingMiddleware::class, RequestLoggingMiddleware::class);
        $container->singleton(BenchmarkMiddleware::class, BenchmarkMiddleware::class);
        $container->singleton(DispatchRequestHandler::class, DispatchRequestHandler::class);
        $container->singleton(CorsMiddleware::class, CorsMiddleware::class);
        $container->singleton(HtmlMinifyMiddleware::class, HtmlMinifyMiddleware::class);
        $container->singleton(OptionsMiddleware::class, OptionsMiddleware::class);
        $container->singleton(MiddlewareResolver::class, MiddlewareResolver::class);
        $container->singleton(MiddlewareStack::class, static fn(): MiddlewareStack => new MiddlewareStack([
            RequestLoggingMiddleware::class,
            BenchmarkMiddleware::class,
            ErrorHandlingMiddleware::class,
            CorsMiddleware::class,
            PoweredByMiddleware::class,
            HtmlMinifyMiddleware::class,
            ApiAuthorizationMiddleware::class,
            OptionsMiddleware::class,
        ]));
        $container->singleton(HtmlMinifier::class, HtmlMinifier::class);

        $container->singleton(HttpRequestInspector::class, HttpRequestInspector::class);
        $container->singleton(HttpLogContext::class, HttpLogContext::class);
    }
}
