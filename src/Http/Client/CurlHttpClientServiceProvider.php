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

final class CurlHttpClientServiceProvider implements ServiceProviderInterface
{
    private const CURL_CLIENT_CLASS = 'Http\\Client\\Curl\\Client';

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
            if (!class_exists(self::CURL_CLIENT_CLASS)) {
                throw new \RuntimeException(
                    'PHP-HTTP cURL client is not installed. Install php-http/curl-client or bind Psr\Http\Client\ClientInterface manually.',
                );
            }

            if (!extension_loaded('curl')) {
                throw new \RuntimeException(
                    'PHP extension "curl" is required by php-http/curl-client.',
                );
            }

            $config = $container->get(HttpClientConfig::class);

            $clientClass = self::CURL_CLIENT_CLASS;
            $client = new $clientClass(
                null,
                null,
                [
                    \CURLOPT_TIMEOUT => (int) round($config->timeout),
                    \CURLOPT_CONNECTTIMEOUT => (int) round($config->connectTimeout),
                    \CURLOPT_SSL_VERIFYPEER => $config->verifySsl,
                    \CURLOPT_SSL_VERIFYHOST => $config->verifySsl ? 2 : 0,
                ],
            );

            if (!$client instanceof ClientInterface) {
                throw new \RuntimeException('Resolved cURL client does not implement PSR-18 ClientInterface.');
            }

            return $client;
        });

        $container->singleton(self::CURL_CLIENT_CLASS, ClientInterface::class);

        if (interface_exists(\Http\Client\HttpClient::class)) {
            $container->singleton(\Http\Client\HttpClient::class, ClientInterface::class);
        }
    }
}
