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
 * Registers the optional Guzzle adapter as the configured PSR-18 client.
 */
final readonly class GuzzleHttpClientServiceProvider implements ServiceProviderInterface
{
    /**
     * Binds client configuration and validates Guzzle availability when the client is first resolved.
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

        $container->alias(\GuzzleHttp\Client::class, ClientInterface::class);

        if (interface_exists(\GuzzleHttp\ClientInterface::class)) {
            $container->alias(\GuzzleHttp\ClientInterface::class, ClientInterface::class);
        }
    }
}
