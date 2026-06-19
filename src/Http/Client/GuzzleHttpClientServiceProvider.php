<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Client;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Http\Config\HttpClientConfig;
use Lemonade\Framework\Http\Config\HttpClientConfigDefinition;
use Lemonade\Framework\Http\Config\HttpClientConfigResolver;
use Psr\Http\Client\ClientInterface;

final class GuzzleHttpClientServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(HttpClientConfigResolver::class, HttpClientConfigResolver::class);
        $container->singleton(HttpClientConfig::class, static function (ContainerInterface $container): HttpClientConfig {
            return $container
                ->get(HttpClientConfigResolver::class)
                ->resolve(...$container->get(ConfigDefinitionRegistry::class)->typedEntriesFor(
                    HttpClientConfigDefinition::moduleKey(),
                    HttpClientConfigDefinition::class,
                ));
        });

        $container->singleton(ClientInterface::class, static function (ContainerInterface $container): ClientInterface {
            if (!class_exists(\GuzzleHttp\Client::class)) {
                throw new \RuntimeException(
                    'No PSR-18 HTTP client is installed. Install guzzlehttp/guzzle or bind Psr\Http\Client\ClientInterface manually.',
                );
            }

            $config = $container->get(HttpClientConfig::class);

            return new \GuzzleHttp\Client([
                'timeout' => $config->timeout,
                'connect_timeout' => $config->connectTimeout,
                'verify' => $config->verifySsl,
            ]);
        });

        $container->singleton(\GuzzleHttp\Client::class, ClientInterface::class);

        if (interface_exists(\GuzzleHttp\ClientInterface::class)) {
            $container->singleton(\GuzzleHttp\ClientInterface::class, ClientInterface::class);
        }
    }
}
