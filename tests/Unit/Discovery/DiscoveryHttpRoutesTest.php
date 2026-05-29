<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Discovery;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Core\CoreServiceProvider;
use Lemonade\Framework\Core\Framework;
use Lemonade\Framework\Discovery\DiscoveryServiceProvider;
use Lemonade\Framework\Filesystem\FilesystemServiceProvider;
use Lemonade\Framework\Http\HttpServiceProvider;
use Lemonade\Framework\Routing\Router;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class DiscoveryHttpRoutesTest extends TestCase
{
    public function testRoutesAreNotRegisteredByDefaultWhenDiscoveryIsDisabled(): void
    {
        $framework = new Framework(
            new Container(),
            new ApplicationContext(Environment::Testing, new Path(__DIR__), DebugMode::disabled()),
        );
        $framework->register(new CoreServiceProvider());
        $framework->register(new FilesystemServiceProvider());
        $framework->register(new HttpServiceProvider());
        $framework->register(new DiscoveryServiceProvider());

        $router = $framework->container()->get(Router::class);
        self::assertFalse($router->hasExplicitRouteForPath('GET', '/robots.txt'));
        self::assertFalse($router->hasExplicitRouteForPath('GET', '/sitemap.xml'));
    }

    public function testRobotsAndSitemapRoutesReturnExpectedContentTypesWhenEnabled(): void
    {
        $framework = new Framework(
            new Container(),
            new ApplicationContext(Environment::Testing, new Path(__DIR__), DebugMode::disabled()),
        );
        $framework->config([
            'discovery' => [
                'robots' => ['enabled' => true],
                'sitemap' => ['enabled' => true],
            ],
        ]);
        $framework->register(new CoreServiceProvider());
        $framework->register(new FilesystemServiceProvider());
        $framework->register(new HttpServiceProvider());
        $framework->register(new DiscoveryServiceProvider());

        $factory = new Psr17Factory();
        $robots = $framework->run($factory->createServerRequest('GET', '/robots.txt'));
        self::assertSame('text/plain; charset=UTF-8', $robots->getHeaderLine('Content-Type'));

        $sitemap = $framework->run($factory->createServerRequest('GET', '/sitemap.xml'));
        self::assertStringContainsString('application/xml', $sitemap->getHeaderLine('Content-Type'));
    }
}
