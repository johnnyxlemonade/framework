<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api;

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
use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Core\ServiceProviderInterface;

final class ApiServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(ApiEndpointRegistry::class, ApiEndpointRegistry::class);
        $container->singleton(ApiEndpointRegistrar::class, ApiEndpointRegistrar::class);
        $container->singleton(ApiAuthenticatorInterface::class, static function (ContainerInterface $container): ApiAuthenticatorInterface {
            /** @var Config $config */
            $config = $container->get(Config::class);

            if (!$config->bool('api.security.static_bearer.enabled', false)) {
                return new NullApiAuthenticator();
            }

            $token = $config->string('api.security.static_bearer.token');
            /** @var list<string> $scopes */
            $scopes = array_values(array_filter(
                $config->array('api.security.static_bearer.scopes', ['api:admin']),
                static fn(mixed $scope): bool => is_string($scope) && trim($scope) !== '',
            ));

            if ($token === null || trim($token) === '') {
                return new NullApiAuthenticator();
            }

            return new StaticBearerTokenAuthenticator(
                token: $token,
                scopes: $scopes !== [] ? $scopes : ['api:admin'],
            );
        });

        $container->singleton(ApiResponseFactory::class, ApiResponseFactory::class);
        $container->singleton(ProblemDetailsFactory::class, ProblemDetailsFactory::class);

        $container->singleton(ScopeVoter::class, ScopeVoter::class);
        $container->singleton(OpenApiGenerator::class, OpenApiGenerator::class);
        $container->singleton(ApiAuthorizationMiddleware::class, ApiAuthorizationMiddleware::class);

        /** @var Config $config */
        $config = $container->get(Config::class);
        if (!$config->bool('api.enabled', true)) {
            return;
        }

        /** @var ApiEndpointRegistry $registry */
        $registry = $container->get(ApiEndpointRegistry::class);

        $frameworkProvider = $container->get(FrameworkApiEndpointProvider::class);
        $this->registerProvider($frameworkProvider, $registry);

        foreach ($config->array('api.endpoint_providers', []) as $providerClass) {
            if (!is_string($providerClass) || !class_exists($providerClass)) {
                throw new \LogicException(sprintf(
                    'Configured API endpoint provider "%s" does not exist.',
                    is_scalar($providerClass) ? (string) $providerClass : get_debug_type($providerClass),
                ));
            }

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
            $config->string('api.prefix', '/api') ?? '/api',
        );
    }

    private function registerProvider(ApiEndpointProviderInterface $provider, ApiEndpointRegistry $registry): void
    {
        $provider->register($registry);
    }
}
