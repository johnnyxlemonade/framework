<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Client;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Psr\Http\Client\ClientInterface;

final class SymfonyHttpClientServiceProvider implements ServiceProviderInterface
{
    private const SYMFONY_PSR18_CLIENT_CLASS = 'Symfony\\Component\\HttpClient\\Psr18Client';
    private const SYMFONY_HTTP_CLIENT_CLASS = 'Symfony\\Component\\HttpClient\\HttpClient';
    private const SYMFONY_HTTP_CLIENT_INTERFACE = 'Symfony\\Contracts\\HttpClient\\HttpClientInterface';

    public function register(ContainerInterface $container): void
    {
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

            $config = $container->get(Config::class);
            $timeout = $config->get('http.client.timeout', 10.0);
            $verifySsl = $config->bool('http.client.verify_ssl', true);

            $httpClientClass = self::SYMFONY_HTTP_CLIENT_CLASS;
            $psr18ClientClass = self::SYMFONY_PSR18_CLIENT_CLASS;
            $httpClient = $httpClientClass::create([
                'timeout' => is_numeric($timeout) ? (float) $timeout : 10.0,
                'verify_peer' => $verifySsl,
                'verify_host' => $verifySsl,
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
                $config = $container->get(Config::class);
                $httpClientClass = self::SYMFONY_HTTP_CLIENT_CLASS;
                $timeout = $config->get('http.client.timeout', 10.0);
                $verifySsl = $config->bool('http.client.verify_ssl', true);

                $httpClient = $httpClientClass::create([
                    'timeout' => is_numeric($timeout) ? (float) $timeout : 10.0,
                    'verify_peer' => $verifySsl,
                    'verify_host' => $verifySsl,
                ]);

                if (!is_object($httpClient)) {
                    throw new \RuntimeException('Resolved Symfony HttpClient must be an object instance.');
                }

                return $httpClient;
            });
        }
    }
}
