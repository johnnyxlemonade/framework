<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Http;

use Lemonade\Framework\Api\Config\ApiConfig;
use Lemonade\Framework\Api\Config\ApiConfigResolver;
use Lemonade\Framework\Api\Http\Middleware\ApiAuthorizationMiddleware;
use Lemonade\Framework\Api\Security\ApiAuthenticatorInterface;
use Lemonade\Framework\Container\CompiledContainerPlan;
use Lemonade\Framework\Container\ContainerBuilder;
use Lemonade\Framework\Container\ServiceLifetime;
use Lemonade\Framework\Core\Http\RequestData;
use Lemonade\Framework\Http\Config\CorsConfig;
use Lemonade\Framework\Http\Config\CorsConfigResolver;
use Lemonade\Framework\Http\Config\ErrorConfig;
use Lemonade\Framework\Http\Config\ErrorConfigResolver;
use Lemonade\Framework\Http\Config\HtmlMinifyConfig;
use Lemonade\Framework\Http\Config\HtmlMinifyConfigResolver;
use Lemonade\Framework\Http\Error\ErrorPageRenderer;
use Lemonade\Framework\Http\HttpServiceProvider;
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
use PHPUnit\Framework\TestCase;

final class HttpServiceProviderTest extends TestCase
{
    public function testRegistersSharedHttpConfigurationAndMiddlewarePolicyAsSingletons(): void
    {
        $plan = $this->plan();

        foreach ([
            HtmlMinifyConfig::class,
            CorsConfig::class,
            ErrorConfig::class,
            MiddlewareStack::class,
        ] as $serviceId) {
            self::assertSame(ServiceLifetime::Singleton, $plan->definition($serviceId)?->lifetime);
        }
    }

    public function testLeavesStatelessConcreteServicesToAutowiring(): void
    {
        $plan = $this->plan();

        foreach ([
            HtmlMinifyConfigResolver::class,
            CorsConfigResolver::class,
            ErrorConfigResolver::class,
            ErrorPageRenderer::class,
            HtmlMinifier::class,
            ErrorHandlingMiddleware::class,
            PoweredByMiddleware::class,
            RequestLoggingMiddleware::class,
            BenchmarkMiddleware::class,
            CorsMiddleware::class,
            HtmlMinifyMiddleware::class,
            OptionsMiddleware::class,
            HttpRequestInspector::class,
            HttpLogContext::class,
        ] as $serviceId) {
            self::assertFalse($plan->hasDefinition($serviceId));
        }
    }

    public function testRegistersRequestBoundServicesAsScoped(): void
    {
        $plan = $this->plan();

        foreach ([
            DispatchRequestHandler::class,
            MiddlewareResolver::class,
            RequestData::class,
        ] as $serviceId) {
            self::assertSame(ServiceLifetime::Scoped, $plan->definition($serviceId)?->lifetime);
        }
    }

    public function testRegistersFallbacksRequiredByTheApiAwareDefaultPipeline(): void
    {
        $plan = $this->plan();

        foreach ([
            ApiConfig::class,
            ApiAuthenticatorInterface::class,
        ] as $serviceId) {
            self::assertSame(ServiceLifetime::Singleton, $plan->definition($serviceId)?->lifetime);
        }

        self::assertFalse($plan->hasDefinition(ApiConfigResolver::class));
        self::assertFalse($plan->hasDefinition(ApiAuthorizationMiddleware::class));
    }

    private function plan(): CompiledContainerPlan
    {
        $builder = new ContainerBuilder();
        (new HttpServiceProvider())->register($builder);

        return $builder->compile();
    }
}
