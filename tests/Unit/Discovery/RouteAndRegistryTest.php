<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Discovery;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Discovery\Sitemap\RouteSitemapProvider;
use Lemonade\Framework\Discovery\Sitemap\SitemapProviderInterface;
use Lemonade\Framework\Discovery\Sitemap\SitemapProviderRegistry;
use Lemonade\Framework\Discovery\Sitemap\SitemapUrl;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Routing\UrlGenerator;
use PHPUnit\Framework\TestCase;

final class RouteAndRegistryTest extends TestCase
{
    public function testRouteProviderBuildsNamedRoutesWithAndWithoutParams(): void
    {
        $router = new Router();
        $router->getNamed('home', '/', 'HomeController@index');
        $router->getNamed('page.detail', '/page/{slug}', 'PageController@detail');

        $config = new Config([
            'discovery' => [
                'sitemap' => [
                    'routes' => [
                        'home',
                        ['name' => 'page.detail', 'params' => ['slug' => 'about'], 'priority' => 0.6],
                    ],
                ],
            ],
        ]);

        $urls = [];
        foreach ((new RouteSitemapProvider($config, new UrlGenerator($router)))->urls() as $url) {
            $urls[] = $url;
        }
        self::assertCount(2, $urls);
        self::assertSame('/', $urls[0]->loc());
        self::assertSame('/page/about', $urls[1]->loc());
    }

    public function testRegistryResolvesProvidersFromContainer(): void
    {
        $container = new Container();
        $container->singleton(TestProvider::class, TestProvider::class);
        $config = new Config([
            'discovery' => [
                'sitemap' => [
                    'providers' => [TestProvider::class],
                    'routes' => [],
                ],
            ],
        ]);

        $routeProvider = new RouteSitemapProvider($config, new UrlGenerator(new Router()));
        $registry = new SitemapProviderRegistry($container, $config, $routeProvider);
        $providers = [];
        foreach ($registry->providers() as $provider) {
            $providers[] = $provider;
        }

        self::assertCount(2, $providers);
        self::assertInstanceOf(TestProvider::class, $providers[1]);
    }
}

final class TestProvider implements SitemapProviderInterface
{
    public function urls(): iterable
    {
        yield SitemapUrl::create('/x');
    }
}
