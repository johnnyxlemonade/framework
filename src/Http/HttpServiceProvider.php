<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Http\Error\ErrorPageRenderer;
use Lemonade\Framework\Http\Logging\HttpLogContext;
use Lemonade\Framework\Http\Middleware\BenchmarkMiddleware;
use Lemonade\Framework\Http\Middleware\DispatchRequestHandler;
use Lemonade\Framework\Http\Middleware\ErrorHandlingMiddleware;
use Lemonade\Framework\Http\Middleware\HtmlMinifyMiddleware;
use Lemonade\Framework\Http\Middleware\PoweredByMiddleware;
use Lemonade\Framework\Http\Middleware\RequestLoggingMiddleware;
use Lemonade\Framework\Http\Request\HttpRequestInspector;
use Lemonade\Framework\Http\Response\HtmlMinifier;

final class HttpServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(ErrorPageRenderer::class, ErrorPageRenderer::class);

        $container->singleton(ErrorHandlingMiddleware::class, ErrorHandlingMiddleware::class);
        $container->singleton(PoweredByMiddleware::class, PoweredByMiddleware::class);
        $container->singleton(RequestLoggingMiddleware::class, RequestLoggingMiddleware::class);
        $container->singleton(BenchmarkMiddleware::class, BenchmarkMiddleware::class);
        $container->singleton(DispatchRequestHandler::class, DispatchRequestHandler::class);
        $container->singleton(HtmlMinifyMiddleware::class, HtmlMinifyMiddleware::class);
        $container->singleton(HtmlMinifier::class, HtmlMinifier::class);

        $container->singleton(HttpRequestInspector::class, HttpRequestInspector::class);
        $container->singleton(HttpLogContext::class, HttpLogContext::class);
    }
}
