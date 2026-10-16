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
 * Registers the optional PHP-HTTP cURL adapter as the configured PSR-18 client.
 */
final readonly class CurlHttpClientServiceProvider implements ServiceProviderInterface
{
    private const string CURL_CLIENT_CLASS = 'Http\\Client\\Curl\\Client';

    /**
     * Binds client configuration and validates cURL dependencies when the client is first resolved.
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

        $container->alias(self::CURL_CLIENT_CLASS, ClientInterface::class);

        if (interface_exists(\Http\Client\HttpClient::class)) {
            $container->alias(\Http\Client\HttpClient::class, ClientInterface::class);
        }
    }
}
