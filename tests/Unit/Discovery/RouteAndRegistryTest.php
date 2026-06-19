<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Discovery;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Discovery\Config\SitemapConfig;
use Lemonade\Framework\Discovery\Config\SitemapRouteConfig;
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

        $config = $this->sitemapConfig(
            routes: [
                new SitemapRouteConfig('home', [], null, null, null),
                new SitemapRouteConfig('page.detail', ['slug' => 'about'], null, null, 0.6),
            ],
        );

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
        $config = $this->sitemapConfig(providers: [TestProvider::class]);

        $routeProvider = new RouteSitemapProvider($config, new UrlGenerator(new Router()));
        $registry = new SitemapProviderRegistry($container, $config, $routeProvider);
        $providers = [];
        foreach ($registry->providers() as $provider) {
            $providers[] = $provider;
        }

        self::assertCount(2, $providers);
        self::assertInstanceOf(TestProvider::class, $providers[1]);
    }

    /**
     * @param list<SitemapRouteConfig> $routes
     * @param list<class-string<SitemapProviderInterface>> $providers
     */
    private function sitemapConfig(array $routes = [], array $providers = []): SitemapConfig
    {
        return new SitemapConfig(
            enabled: false,
            route: '/sitemap.xml',
            cliOutput: true,
            mode: 'stream',
            baseUrl: null,
            routes: $routes,
            providers: $providers,
            cachePath: 'storage/cache/discovery',
            filename: 'sitemap.xml',
            indexFilename: 'sitemap.xml',
            gzip: false,
            maxUrlsPerFile: 50000,
            maxUncompressedBytes: 52428800,
            deduplicate: false,
            onInvalidUrl: 'fail',
        );
    }
}

final class TestProvider implements SitemapProviderInterface
{
    public function urls(): iterable
    {
        yield SitemapUrl::create('/x');
    }
}
