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

final class SymfonyHttpClientServiceProvider implements ServiceProviderInterface
{
    private const SYMFONY_PSR18_CLIENT_CLASS = 'Symfony\\Component\\HttpClient\\Psr18Client';
    private const SYMFONY_HTTP_CLIENT_CLASS = 'Symfony\\Component\\HttpClient\\HttpClient';
    private const SYMFONY_HTTP_CLIENT_INTERFACE = 'Symfony\\Contracts\\HttpClient\\HttpClientInterface';

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
            if (!class_exists(self::SYMFONY_PSR18_CLIENT_CLASS)) {
                throw new \RuntimeException(
                    'Symfony PSR-18 HTTP client is not installed. Install symfony/http-client or bind Psr\Http\Client\ClientInterface manually.',
                );
            }

            if (!class_exists(self::SYMFONY_HTTP_CLIENT_CLASS)) {
                throw new \RuntimeException(
                    'Symfony HttpClient is not installed. Install symfony/http-client.',
                );
            }

            $config = $container->get(HttpClientConfig::class);

            $httpClientClass = self::SYMFONY_HTTP_CLIENT_CLASS;
            $psr18ClientClass = self::SYMFONY_PSR18_CLIENT_CLASS;
            $httpClient = $httpClientClass::create([
                'timeout' => $config->timeout,
                'verify_peer' => $config->verifySsl,
                'verify_host' => $config->verifySsl,
            ]);
            $client = new $psr18ClientClass($httpClient);
            if (!$client instanceof ClientInterface) {
                throw new \RuntimeException('Resolved Symfony PSR-18 client does not implement ClientInterface.');
            }

            return $client;
        });

        $container->singleton(self::SYMFONY_PSR18_CLIENT_CLASS, ClientInterface::class);

        if (interface_exists(self::SYMFONY_HTTP_CLIENT_INTERFACE)) {
            $container->singleton(self::SYMFONY_HTTP_CLIENT_INTERFACE, static function (ContainerInterface $container): object {
                $config = $container->get(HttpClientConfig::class);
                $httpClientClass = self::SYMFONY_HTTP_CLIENT_CLASS;

                $httpClient = $httpClientClass::create([
                    'timeout' => $config->timeout,
                    'verify_peer' => $config->verifySsl,
                    'verify_host' => $config->verifySsl,
                ]);

                if (!is_object($httpClient)) {
                    throw new \RuntimeException('Resolved Symfony HttpClient must be an object instance.');
                }

                return $httpClient;
            });
        }
    }
}
