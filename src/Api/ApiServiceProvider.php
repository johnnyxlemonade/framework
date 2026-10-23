<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api;

use Lemonade\Framework\Api\Config\ApiConfig;
use Lemonade\Framework\Api\Config\ApiConfigDefinition;
use Lemonade\Framework\Api\Config\ApiConfigResolver;
use Lemonade\Framework\Api\Endpoint\ApiEndpointProviderInterface;
use Lemonade\Framework\Api\Endpoint\ApiEndpointRegistrar;
use Lemonade\Framework\Api\Endpoint\ApiEndpointRegistry;
use Lemonade\Framework\Api\Framework\FrameworkApiEndpointProvider;
use Lemonade\Framework\Api\Http\Middleware\ApiAuthorizationMiddleware;
use Lemonade\Framework\Api\Http\Response\ApiExceptionResponseResolver;
use Lemonade\Framework\Api\Security\ApiAuthenticatorInterface;
use Lemonade\Framework\Api\Security\NullApiAuthenticator;
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

/**
 * Composes API configuration, endpoint registration, authorization and response services.
 *
 * HTTP boot contributes middleware and routes, while CLI boot only validates endpoint providers.
 */
final readonly class ApiServiceProvider implements ServiceProviderInterface, BootableServiceProviderInterface
{
    /**
     * Registers configuration, shared state and interface bindings while leaving stateless concrete services to autowiring.
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $container->singleton(ApiConfig::class, static function (ContainerInterface $container): ApiConfig {
            return $container
                ->get(ApiConfigResolver::class)
                ->resolve(...$container->get(ConfigDefinitionRegistry::class)->typedEntriesFor(
                    ApiConfigDefinition::moduleKey(),
                    ApiConfigDefinition::class,
                ));
        });

        $container->singleton(ApiEndpointRegistry::class, ApiEndpointRegistry::class);
        $container->singleton(ApiAuthenticatorInterface::class, static function (ContainerInterface $container): ApiAuthenticatorInterface {
            $config = $container->get(ApiConfig::class);
            $security = $config->security();
            $staticBearer = $security->staticBearer();

            if ($staticBearer === null) {
                return new NullApiAuthenticator();
            }

            return new StaticBearerTokenAuthenticator(
                token: $staticBearer->token(),
                scopes: $staticBearer->scopes(),
            );
        });

        $container->alias(
            ExceptionResponseResolverInterface::class,
            ApiExceptionResponseResolver::class,
        );
    }

    /**
     * Registers enabled framework and application endpoints for HTTP or validates providers for CLI.
     *
     * @throws LogicException When a configured endpoint provider violates its required contract.
     */
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

        /**
         * @var ApiEndpointRegistry $registry
         */
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
     * Rejects configured provider classes that cannot contribute API endpoints.
     *
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
