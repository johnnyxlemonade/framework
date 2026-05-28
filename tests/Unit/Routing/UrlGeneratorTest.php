<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Routing;

use Lemonade\Framework\Localization\LocaleResolverInterface;
use Lemonade\Framework\Routing\LocaleUrlStrategyInterface;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Routing\UrlGenerator;
use PHPUnit\Framework\TestCase;

use function strtolower;

final class UrlGeneratorTest extends TestCase
{
    public function testRouteReturnsUrlFromRouter(): void
    {
        $router = new Router();
        $router->getNamed('posts.show', '/posts/{id}', 'PostController@show');

        $generator = new UrlGenerator($router);

        self::assertSame('/posts/7', $generator->route('posts.show', ['id' => '7']));
    }

    public function testLocalizedRouteDefaultLocaleUsesBaseRoute(): void
    {
        $router = new Router();
        $router->localizedGroup(static function (Router $router): void {
            $router->getNamed('posts.show', '/posts/{id}', 'PostController@show');
        });
        $resolver = new LocaleResolverSpy('en');

        $generator = new UrlGenerator($router, $resolver, new StrategySpy());
        $url = $generator->localizedRoute('posts.show', ['id' => '15']);

        self::assertSame('/posts/15', $url);
        self::assertSame(1, $resolver->calls);
    }

    public function testLocalizedRouteNonDefaultLocaleUsesLocalizedRoute(): void
    {
        $router = new Router();
        $router->localizedGroup(static function (Router $router): void {
            $router->getNamed('posts.show', '/posts/{id}', 'PostController@show');
        });
        $resolver = new LocaleResolverSpy('cs');

        $generator = new UrlGenerator($router, $resolver, new StrategySpy());
        $url = $generator->localizedRoute('posts.show', ['id' => '15']);

        self::assertSame('/cs/posts/15', $url);
        self::assertSame(1, $resolver->calls);
    }

    public function testLocalizedRouteRespectsExplicitDefaultLocaleWithoutPrefix(): void
    {
        $router = new Router();
        $router->localizedGroup(static function (Router $router): void {
            $router->getNamed('posts.show', '/posts/{id}', 'PostController@show');
        });

        $generator = new UrlGenerator($router, null, new StrategySpy());
        $url = $generator->localizedRoute('posts.show', [
            'locale' => 'en',
            'id' => '15',
        ]);

        self::assertSame('/posts/15', $url);
    }

    public function testLocalizedRouteKeepsQueryString(): void
    {
        $router = new Router();
        $router->localizedGroup(static function (Router $router): void {
            $router->getNamed('posts.show', '/posts/{id}', 'PostController@show');
        });
        $resolver = new LocaleResolverSpy('en');

        $generator = new UrlGenerator($router, $resolver, new StrategySpy());
        $url = $generator->localizedRoute('posts.show', [
            'id' => '15',
            'tab' => 'meta',
        ]);

        self::assertSame('/posts/15?tab=meta', $url);
    }

    public function testNonLocalizedRouteStaysWithoutLocale(): void
    {
        $router = new Router();
        $router->getNamed('posts.show', '/posts/{id}', 'PostController@show');
        $resolver = new LocaleResolverSpy('cs');

        $generator = new UrlGenerator($router, $resolver, new StrategySpy());
        $url = $generator->route('posts.show', ['id' => '15']);

        self::assertSame('/posts/15', $url);
        self::assertSame(0, $resolver->calls);
    }

    public function testLocalizedRouteFallsBackToBaseWhenLocalizedVariantIsMissing(): void
    {
        $router = new Router();
        $router->getNamed('posts.show', '/posts/{id}', 'PostController@show');
        $resolver = new LocaleResolverSpy('cs');

        $generator = new UrlGenerator($router, $resolver, new StrategySpy());
        $url = $generator->localizedRoute('posts.show', ['id' => '15']);

        self::assertSame('/posts/15', $url);
    }
}

final class LocaleResolverSpy implements LocaleResolverInterface
{
    public int $calls = 0;

    public function __construct(
        private readonly string $locale,
    ) {}

    public function resolve(): string
    {
        $this->calls++;

        return $this->locale;
    }
}

final class StrategySpy implements LocaleUrlStrategyInterface
{
    public function enabled(): bool
    {
        return true;
    }

    public function localeParameter(): string
    {
        return 'locale';
    }

    public function localizedRouteName(string $baseRouteName): string
    {
        return 'localized.' . $baseRouteName;
    }

    public function shouldUseLocalizedRoute(string $locale): bool
    {
        return strtolower($locale) !== 'en';
    }
}
