<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api;

use Lemonade\Framework\Api\Config\ApiConfig;
use Lemonade\Framework\Api\Config\ApiConfigDefinition;
use Lemonade\Framework\Api\Config\ApiConfigResolver;
use Lemonade\Framework\Api\Documentation\DocsController;
use Lemonade\Framework\Api\Documentation\OpenApiController;
use Lemonade\Framework\Api\Documentation\OpenApiGenerator;
use Lemonade\Framework\Api\Endpoint\ApiEndpointProviderInterface;
use Lemonade\Framework\Api\Endpoint\ApiEndpointRegistrar;
use Lemonade\Framework\Api\Endpoint\ApiEndpointRegistry;
use Lemonade\Framework\Api\Endpoint\ApiEndpointRequestResolver;
use Lemonade\Framework\Api\Endpoint\ApiRoutePathResolver;
use Lemonade\Framework\Api\Framework\FrameworkApiEndpointProvider;
use Lemonade\Framework\Api\Framework\Health\HealthController;
use Lemonade\Framework\Api\Http\Middleware\ApiAuthorizationMiddleware;
use Lemonade\Framework\Api\Http\Response\ApiExceptionResponseResolver;
use Lemonade\Framework\Api\Http\Response\ApiResponseFactory;
use Lemonade\Framework\Api\Http\Response\ProblemDetailsFactory;
use Lemonade\Framework\Api\Security\ApiAuthenticatorInterface;
use Lemonade\Framework\Api\Security\NullApiAuthenticator;
use Lemonade\Framework\Api\Security\ScopeVoter;
use Lemonade\Framework\Api\Security\StaticBearerTokenAuthenticator;
use Lemonade\Framework\Cli\CommandRegistry;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\BootableServiceProviderInterface;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Http\Error\ExceptionResponseResolverInterface;
use Lemonade\Framework\Http\Middleware\MiddlewareStack;
use Lemonade\Framework\Http\Middleware\OptionsMiddleware;
use LogicException;

final class ApiServiceProvider implements ServiceProviderInterface, BootableServiceProviderInterface
{
    public function register(ContainerBuilderInterface $container): void
    {
        $container->singleton(ApiConfigResolver::class, ApiConfigResolver::class);
        $container->singleton(ApiConfig::class, static function (ContainerInterface $container): ApiConfig {
            return $container
                ->get(ApiConfigResolver::class)
                ->resolve(...$container->get(ConfigDefinitionRegistry::class)->typedEntriesFor(
                    ApiConfigDefinition::moduleKey(),
                    ApiConfigDefinition::class,
                ));
        });

        $container->singleton(ApiEndpointRegistry::class, ApiEndpointRegistry::class);
        $container->singleton(ApiRoutePathResolver::class, ApiRoutePathResolver::class);
        $container->singleton(ApiEndpointRegistrar::class, ApiEndpointRegistrar::class);
        $container->singleton(ApiEndpointRequestResolver::class, ApiEndpointRequestResolver::class);
        $container->singleton(ApiAuthenticatorInterface::class, static function (ContainerInterface $container): ApiAuthenticatorInterface {
            $config = $container->get(ApiConfig::class);
            $security = $config->security();

            if ($security->staticBearer === null) {
                return new NullApiAuthenticator();
            }

            return new StaticBearerTokenAuthenticator(
                token: $security->staticBearer->token,
                scopes: $security->staticBearer->scopes,
            );
        });

        $container->singleton(ApiResponseFactory::class, ApiResponseFactory::class);
        $container->singleton(ProblemDetailsFactory::class, ProblemDetailsFactory::class);
        $container->alias(
            ExceptionResponseResolverInterface::class,
            ApiExceptionResponseResolver::class,
        );

        $container->singleton(ScopeVoter::class, ScopeVoter::class);
        $container->singleton(OpenApiGenerator::class, OpenApiGenerator::class);
        $container->singleton(FrameworkApiEndpointProvider::class, FrameworkApiEndpointProvider::class);
        $container->singleton(HealthController::class, HealthController::class);
        $container->singleton(OpenApiController::class, OpenApiController::class);
        $container->singleton(DocsController::class, DocsController::class);
        $container->singleton(ApiAuthorizationMiddleware::class, ApiAuthorizationMiddleware::class);
    }

    public function boot(ContainerInterface $container): void
    {
        $config = $container->get(ApiConfig::class);
        $endpointProviders = $config->endpointProviders();

        if ($this->isCliRuntime($container)) {
            $this->assertConfiguredProvidersImplementInterface($endpointProviders);

            return;
        }

        if (!$config->enabled()) {
            return;
        }

        $this->contributeHttpMiddleware($container);

        /** @var ApiEndpointRegistry $registry */
        $registry = $container->get(ApiEndpointRegistry::class);

        $frameworkProvider = $container->get(FrameworkApiEndpointProvider::class);
        $this->registerProvider($frameworkProvider, $registry);

        foreach ($endpointProviders as $providerClass) {
            $provider = $container->get($providerClass);

            if (!$provider instanceof ApiEndpointProviderInterface) {
                throw new \LogicException(sprintf(
                    'Configured API endpoint provider "%s" must implement %s.',
                    $providerClass,
                    ApiEndpointProviderInterface::class,
                ));
            }

            $this->registerProvider($provider, $registry);
        }

        $container->get(ApiEndpointRegistrar::class)->registerRoutes(
            $config->prefix(),
        );
    }

    private function contributeHttpMiddleware(ContainerInterface $container): void
    {
        if (!$container->has(MiddlewareStack::class)) {
            return;
        }

        $stack = $container->get(MiddlewareStack::class);
        if (in_array(ApiAuthorizationMiddleware::class, $stack->all(), true)) {
            return;
        }

        if (in_array(OptionsMiddleware::class, $stack->all(), true)) {
            $stack->insertBefore(
                OptionsMiddleware::class,
                ApiAuthorizationMiddleware::class,
            );

            return;
        }

        $stack->add(ApiAuthorizationMiddleware::class);
    }

    private function isCliRuntime(ContainerInterface $container): bool
    {
        return $container->has(CommandRegistry::class);
    }

    /**
     * @param list<class-string>|list<string> $providerClasses
     */
    private function assertConfiguredProvidersImplementInterface(array $providerClasses): void
    {
        foreach ($providerClasses as $providerClass) {
            if (!is_string($providerClass) || !is_subclass_of($providerClass, ApiEndpointProviderInterface::class)) {
                throw new LogicException(sprintf(
                    'Configured API endpoint provider "%s" must implement %s.',
                    is_string($providerClass) ? $providerClass : get_debug_type($providerClass),
                    ApiEndpointProviderInterface::class,
                ));
            }
        }
    }

    private function registerProvider(ApiEndpointProviderInterface $provider, ApiEndpointRegistry $registry): void
    {
        $provider->register($registry);
    }
}
