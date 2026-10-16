<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Client;

use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Http\Client\Internal\HttpClientConfigServices;
use Lemonade\Framework\Http\Config\HttpClientConfig;
use LogicException;
use Psr\Http\Client\ClientInterface;

/**
 * Registers the optional Symfony adapter as the configured PSR-18 client.
 */
final readonly class SymfonyHttpClientServiceProvider implements ServiceProviderInterface
{
    private const string SYMFONY_PSR18_CLIENT_CLASS = 'Symfony\\Component\\HttpClient\\Psr18Client';
    private const string SYMFONY_HTTP_CLIENT_CLASS = 'Symfony\\Component\\HttpClient\\HttpClient';
    private const string SYMFONY_HTTP_CLIENT_INTERFACE = 'Symfony\\Contracts\\HttpClient\\HttpClientInterface';

    /**
     * Binds configured Symfony clients and validates optional dependencies when they are first resolved.
     */
    public function register(ContainerBuilderInterface $container): void
    {
        if ($container->isBound(ClientInterface::class)) {
            throw new LogicException(sprintf(
                'Cannot register %s because %s is already bound. Configure exactly one PSR-18 transport provider.',
                self::class,
                ClientInterface::class,
            ));
        }

        HttpClientConfigServices::register($container);

        $container->singleton(
            self::SYMFONY_HTTP_CLIENT_INTERFACE,
            static function (ContainerInterface $container): object {
                if (!class_exists(self::SYMFONY_HTTP_CLIENT_CLASS)) {
                    throw new \RuntimeException(
                        'Symfony HttpClient is not installed. Install symfony/http-client.',
                    );
                }

                if (!interface_exists(self::SYMFONY_HTTP_CLIENT_INTERFACE)) {
                    throw new \RuntimeException(
                        'Symfony HttpClient contracts are not installed. Install symfony/http-client-contracts.',
                    );
                }

                $config = $container->get(HttpClientConfig::class);
                $httpClientClass = self::SYMFONY_HTTP_CLIENT_CLASS;
                $httpClient = $httpClientClass::create([
                    'timeout' => $config->timeout,
                    'verify_peer' => $config->verifySsl,
                    'verify_host' => $config->verifySsl,
                ]);

                $httpClientInterface = self::SYMFONY_HTTP_CLIENT_INTERFACE;
                if (!$httpClient instanceof $httpClientInterface) {
                    throw new \RuntimeException(sprintf(
                        'Resolved Symfony HttpClient does not implement %s.',
                        self::SYMFONY_HTTP_CLIENT_INTERFACE,
                    ));
                }

                return $httpClient;
            },
        );

        $container->singleton(ClientInterface::class, static function (ContainerInterface $container): ClientInterface {
            if (!class_exists(self::SYMFONY_PSR18_CLIENT_CLASS)) {
                throw new \RuntimeException(
                    'Symfony PSR-18 HTTP client is not installed. Install symfony/http-client or bind Psr\Http\Client\ClientInterface manually.',
                );
            }

            $psr18ClientClass = self::SYMFONY_PSR18_CLIENT_CLASS;
            $httpClient = $container->get(self::SYMFONY_HTTP_CLIENT_INTERFACE);

            return new $psr18ClientClass($httpClient);
        });

        $container->alias(self::SYMFONY_PSR18_CLIENT_CLASS, ClientInterface::class);
    }
}
