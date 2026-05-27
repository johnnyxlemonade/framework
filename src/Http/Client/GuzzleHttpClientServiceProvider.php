<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Client;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Psr\Http\Client\ClientInterface;

final class GuzzleHttpClientServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(ClientInterface::class, static function (ContainerInterface $container): ClientInterface {
            if (!class_exists(\GuzzleHttp\Client::class)) {
                throw new \RuntimeException(
                    'No PSR-18 HTTP client is installed. Install guzzlehttp/guzzle or bind Psr\Http\Client\ClientInterface manually.',
                );
            }

            $config = $container->get(Config::class);
            $timeout = $config->get('http.client.timeout', 10.0);
            $connectTimeout = $config->get('http.client.connect_timeout', 5.0);

            return new \GuzzleHttp\Client([
                'timeout' => is_numeric($timeout) ? (float) $timeout : 10.0,
                'connect_timeout' => is_numeric($connectTimeout) ? (float) $connectTimeout : 5.0,
                'verify' => $config->bool('http.client.verify_ssl', true),
            ]);
        });

        $container->singleton(\GuzzleHttp\Client::class, ClientInterface::class);

        if (interface_exists(\GuzzleHttp\ClientInterface::class)) {
            $container->singleton(\GuzzleHttp\ClientInterface::class, ClientInterface::class);
        }
    }
}
