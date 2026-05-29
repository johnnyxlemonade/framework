<?php

declare(strict_types=1);

namespace Lemonade\Framework\Discovery\Sitemap;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config;

final class SitemapProviderRegistry
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly Config $config,
        private readonly RouteSitemapProvider $routeProvider,
    ) {}

    /**
     * @return iterable<SitemapProviderInterface>
     */
    public function providers(): iterable
    {
        yield $this->routeProvider;

        foreach ($this->config->array('discovery.sitemap.providers') as $providerClass) {
            if (!is_string($providerClass) || !class_exists($providerClass)) {
                $label = is_scalar($providerClass) ? (string) $providerClass : get_debug_type($providerClass);
                throw new SitemapException(sprintf('Sitemap provider class "%s" does not exist.', $label));
            }
            /** @var class-string<SitemapProviderInterface> $providerClass */

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
