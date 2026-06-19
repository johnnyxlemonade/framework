<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api;

use Lemonade\Framework\Api\Config\ApiConfig;
use Lemonade\Framework\Api\Config\ApiConfigDefinition;
use Lemonade\Framework\Api\Config\ApiConfigResolver;
use Lemonade\Framework\Api\Documentation\OpenApiGenerator;
use Lemonade\Framework\Api\Endpoint\ApiEndpointProviderInterface;
use Lemonade\Framework\Api\Endpoint\ApiEndpointRegistrar;
use Lemonade\Framework\Api\Endpoint\ApiEndpointRegistry;
use Lemonade\Framework\Api\Framework\FrameworkApiEndpointProvider;
use Lemonade\Framework\Api\Http\Middleware\ApiAuthorizationMiddleware;
use Lemonade\Framework\Api\Http\Response\ApiResponseFactory;
use Lemonade\Framework\Api\Http\Response\ProblemDetailsFactory;
use Lemonade\Framework\Api\Security\ApiAuthenticatorInterface;
use Lemonade\Framework\Api\Security\NullApiAuthenticator;
use Lemonade\Framework\Api\Security\ScopeVoter;
use Lemonade\Framework\Api\Security\StaticBearerTokenAuthenticator;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\ServiceProviderInterface;

final class ApiServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
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
        $container->singleton(ApiEndpointRegistrar::class, ApiEndpointRegistrar::class);
        $container->singleton(ApiAuthenticatorInterface::class, static function (ContainerInterface $container): ApiAuthenticatorInterface {
            $config = $container->get(ApiConfig::class);

            if ($config->security->staticBearer === null) {
                return new NullApiAuthenticator();
            }

            return new StaticBearerTokenAuthenticator(
                token: $config->security->staticBearer->token,
                scopes: $config->security->staticBearer->scopes,
            );
        });

        $container->singleton(ApiResponseFactory::class, ApiResponseFactory::class);
        $container->singleton(ProblemDetailsFactory::class, ProblemDetailsFactory::class);

        $container->singleton(ScopeVoter::class, ScopeVoter::class);
        $container->singleton(OpenApiGenerator::class, OpenApiGenerator::class);
        $container->singleton(FrameworkApiEndpointProvider::class, FrameworkApiEndpointProvider::class);
        $container->singleton(ApiAuthorizationMiddleware::class, ApiAuthorizationMiddleware::class);

        $config = $container->get(ApiConfig::class);
        if (!$config->enabled) {
            return;
        }

        /** @var ApiEndpointRegistry $registry */
        $registry = $container->get(ApiEndpointRegistry::class);

        $frameworkProvider = $container->get(FrameworkApiEndpointProvider::class);
        $this->registerProvider($frameworkProvider, $registry);

        foreach ($config->endpointProviders as $providerClass) {
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
            $config->prefix,
        );
    }

    private function registerProvider(ApiEndpointProviderInterface $provider, ApiEndpointRegistry $registry): void
    {
        $provider->register($registry);
    }
}
