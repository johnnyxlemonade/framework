<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Http;

use Lemonade\Framework\Api\Config\ApiConfig;
use Lemonade\Framework\Api\Http\Middleware\ApiAuthorizationMiddleware;
use Lemonade\Framework\Api\Security\ApiAuthenticatorInterface;
use Lemonade\Framework\Container\CompiledContainerPlan;
use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\ContainerBuilder;
use Lemonade\Framework\Container\ServiceLifetime;
use Lemonade\Framework\Core\Http\RequestData;
use Lemonade\Framework\Http\Config\CorsConfig;
use Lemonade\Framework\Http\Config\ErrorConfig;
use Lemonade\Framework\Http\Config\HtmlMinifyConfig;
use Lemonade\Framework\Http\Bags\ServerBag;
use Lemonade\Framework\Http\HttpServiceProvider;
use Lemonade\Framework\Http\Middleware\DispatchRequestHandler;
use Lemonade\Framework\Http\Middleware\MiddlewareResolver;
use Lemonade\Framework\Http\Middleware\MiddlewareStack;
use Lemonade\Framework\Http\Request\HttpRequestInspector;
use Lemonade\Framework\Http\Request\ServerHelper;
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

    public function testRegistersRequestBoundServicesAsScoped(): void
    {
        $plan = $this->plan();

        foreach ([
            DispatchRequestHandler::class,
            MiddlewareResolver::class,
            RequestData::class,
            HttpRequestInspector::class,
            ServerHelper::class,
            ServerBag::class,
        ] as $serviceId) {
            self::assertSame(ServiceLifetime::Scoped, $plan->definition($serviceId)?->lifetime);
        }
    }

    public function testDoesNotRegisterApiServicesOrMiddleware(): void
    {
        $plan = $this->plan();

        foreach ([
            ApiConfig::class,
            ApiAuthenticatorInterface::class,
        ] as $serviceId) {
            self::assertFalse($plan->hasDefinition($serviceId));
        }

        self::assertFalse($plan->hasDefinition(ApiAuthorizationMiddleware::class));

        $container = new Container();
        (new HttpServiceProvider())->register($container);

        $apiMiddleware = array_filter(
            $container->get(MiddlewareStack::class)->all(),
            static fn(string $middleware): bool => str_starts_with($middleware, 'Lemonade\\Framework\\Api\\'),
        );

        self::assertSame([], array_values($apiMiddleware));
    }

    private function plan(): CompiledContainerPlan
    {
        $builder = new ContainerBuilder();
        (new HttpServiceProvider())->register($builder);

        return $builder->compile();
    }
}
