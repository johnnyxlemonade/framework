<?php

declare(strict_types=1);

namespace Lemonade\Framework\Discovery;

use Lemonade\Framework\Cli\CommandRegistry;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Discovery\Config\DiscoveryConfig;
use Lemonade\Framework\Discovery\Config\DiscoveryConfigDefinition;
use Lemonade\Framework\Discovery\Config\DiscoveryConfigResolver;
use Lemonade\Framework\Discovery\Config\RobotsConfig;
use Lemonade\Framework\Discovery\Config\SitemapConfig;
use Lemonade\Framework\Discovery\Console\GenerateSitemapCommand;
use Lemonade\Framework\Discovery\Robots\RobotsController;
use Lemonade\Framework\Discovery\Robots\RobotsTxtGenerator;
use Lemonade\Framework\Discovery\Sitemap\RouteSitemapProvider;
use Lemonade\Framework\Discovery\Sitemap\SitemapController;
use Lemonade\Framework\Discovery\Sitemap\SitemapFileGenerator;
use Lemonade\Framework\Discovery\Sitemap\SitemapGenerator;
use Lemonade\Framework\Discovery\Sitemap\SitemapIndexGenerator;
use Lemonade\Framework\Discovery\Sitemap\SitemapProviderRegistry;
use Lemonade\Framework\Filesystem\Contract\DirectoryManagerInterface;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Support\BaseUrlResolver;
use Psr\Log\LoggerInterface;

final class DiscoveryServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(DiscoveryConfigResolver::class, DiscoveryConfigResolver::class);
        $container->singleton(DiscoveryConfig::class, static function (ContainerInterface $container): DiscoveryConfig {
            return $container
                ->get(DiscoveryConfigResolver::class)
                ->resolve(...$container->get(ConfigDefinitionRegistry::class)->typedEntriesFor(
                    DiscoveryConfigDefinition::moduleKey(),
                    DiscoveryConfigDefinition::class,
                ));
        });
        $container->singleton(RobotsConfig::class, static fn(ContainerInterface $container): RobotsConfig => $container->get(DiscoveryConfig::class)->robots);
        $container->singleton(SitemapConfig::class, static fn(ContainerInterface $container): SitemapConfig => $container->get(DiscoveryConfig::class)->sitemap);

        $container->singleton(RobotsTxtGenerator::class, RobotsTxtGenerator::class);
        $container->singleton(SitemapIndexGenerator::class, SitemapIndexGenerator::class);
        $container->singleton(RouteSitemapProvider::class, RouteSitemapProvider::class);
        $container->singleton(SitemapProviderRegistry::class, SitemapProviderRegistry::class);
        $container->singleton(SitemapGenerator::class, static function (ContainerInterface $container): SitemapGenerator {
            return new SitemapGenerator(
                $container->get(SitemapProviderRegistry::class),
                $container->get(BaseUrlResolver::class),
                $container->get(SitemapConfig::class),
                $container->isBound(LoggerInterface::class) ? $container->get(LoggerInterface::class) : null,
            );
        });
        $container->singleton(SitemapFileGenerator::class, static function (ContainerInterface $container): SitemapFileGenerator {
            return new SitemapFileGenerator(
                $container->get(SitemapGenerator::class),
                $container->get(SitemapIndexGenerator::class),
                $container->get(SitemapConfig::class),
                $container->get(ApplicationContext::class),
                $container->get(DirectoryManagerInterface::class),
                $container->isBound(LoggerInterface::class) ? $container->get(LoggerInterface::class) : null,
            );
        });

        $container->singleton(GenerateSitemapCommand::class, GenerateSitemapCommand::class);
        $container->singleton(RobotsController::class, RobotsController::class);
        $container->singleton(SitemapController::class, SitemapController::class);

        $config = $container->get(DiscoveryConfig::class);

        if ($container->isBound(CommandRegistry::class)) {
            $container->get(CommandRegistry::class)->register(GenerateSitemapCommand::class);
        }

        if (!$container->isBound(Router::class)) {
            return;
        }

        $router = $container->get(Router::class);

        if ($config->robots->enabled) {
            $router->get($config->robots->route, RobotsController::class . '@index');
        }
        if ($config->sitemap->enabled) {
            $router->get($config->sitemap->route, SitemapController::class . '@index');
        }
    }
}
