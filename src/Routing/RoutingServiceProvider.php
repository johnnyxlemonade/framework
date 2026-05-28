<?php

declare(strict_types=1);

namespace Lemonade\Framework\Routing;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Localization\LocaleResolverInterface;

final class RoutingServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(LocaleUrlStrategyInterface::class, static function (ContainerInterface $container): LocaleUrlStrategyInterface {
            return new ConfigLocaleUrlStrategy(
                $container->get(Config::class),
            );
        });

        $container->singleton(UrlGenerator::class, static function (ContainerInterface $container): UrlGenerator {
            $localeResolver = $container->isBound(LocaleResolverInterface::class)
                ? $container->get(LocaleResolverInterface::class)
                : null;

            return new UrlGenerator(
                $container->get(Router::class),
                $localeResolver instanceof LocaleResolverInterface ? $localeResolver : null,
                $container->get(LocaleUrlStrategyInterface::class),
            );
        });
        $container->singleton('url', UrlGenerator::class);
    }
}
