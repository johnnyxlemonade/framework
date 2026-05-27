<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Client;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Psr\Http\Client\ClientInterface;

final class CurlHttpClientServiceProvider implements ServiceProviderInterface
{
    private const CURL_CLIENT_CLASS = 'Http\\Client\\Curl\\Client';

    public function register(ContainerInterface $container): void
    {
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

            $config = $container->get(Config::class);
            $timeout = $config->int('http.client.timeout', 10);
            $connectTimeout = $config->int('http.client.connect_timeout', 5);
            $verifySsl = $config->bool('http.client.verify_ssl', true);

            $clientClass = self::CURL_CLIENT_CLASS;
            $client = new $clientClass(
                null,
                null,
                [
                    \CURLOPT_TIMEOUT => $timeout,
                    \CURLOPT_CONNECTTIMEOUT => $connectTimeout,
                    \CURLOPT_SSL_VERIFYPEER => $verifySsl,
                    \CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
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
