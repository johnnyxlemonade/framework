<?php

declare(strict_types=1);

namespace Lemonade\Framework\Discovery\Sitemap;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Discovery\Config\SitemapConfig;

final class SitemapProviderRegistry
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly SitemapConfig $config,
        private readonly RouteSitemapProvider $routeProvider,
    ) {}

    /**
     * @return iterable<SitemapProviderInterface>
     */
    public function providers(): iterable
    {
        yield $this->routeProvider;

        foreach ($this->config->providers as $providerClass) {
            $resolved = $this->container->get($providerClass);
            if (!$resolved instanceof SitemapProviderInterface) {
                throw new SitemapException(sprintf(
                    'Sitemap provider "%s" must implement %s.',
                    $providerClass,
                    SitemapProviderInterface::class,
                ));
            }

            yield $resolved;
        }
    }
}
