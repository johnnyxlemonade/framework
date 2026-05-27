<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Routing;

use InvalidArgumentException;
use Lemonade\Framework\Localization\LocaleResolverInterface;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Routing\UrlGenerator;
use PHPUnit\Framework\TestCase;

final class UrlGeneratorTest extends TestCase
{
    public function testRouteReturnsUrlFromRouter(): void
    {
        $router = new Router();
        $router->getNamed('posts.show', '/posts/{id}', 'PostController@show');

        $generator = new UrlGenerator($router);

        self::assertSame('/posts/7', $generator->route('posts.show', ['id' => '7']));
    }

    public function testMissingLocaleIsAutoFilledFromLocaleResolver(): void
    {
        $router = new Router();
        $router->getNamed('localized.post', '/{locale}/posts/{id}', 'PostController@show');
        $resolver = new LocaleResolverSpy('cs');

        $generator = new UrlGenerator($router, $resolver);
        $url = $generator->route('localized.post', ['id' => '15']);

        self::assertSame('/cs/posts/15', $url);
        self::assertSame(1, $resolver->calls);
    }

    public function testMissingNonLocaleParameterStillThrowsException(): void
    {
        $router = new Router();
        $router->getNamed('localized.post', '/{locale}/posts/{id}', 'PostController@show');
        $resolver = new LocaleResolverSpy('cs');
        $generator = new UrlGenerator($router, $resolver);

        $this->expectException(InvalidArgumentException::class);
        $generator->route('localized.post', []);
    }

    public function testProvidedLocaleSkipsLocaleResolver(): void
    {
        $router = new Router();
        $router->getNamed('localized.post', '/{locale}/posts/{id}', 'PostController@show');
        $resolver = new LocaleResolverSpy('cs');
        $generator = new UrlGenerator($router, $resolver);

        $url = $generator->route('localized.post', [
            'locale' => 'en',
            'id' => '9',
        ]);

        self::assertSame('/en/posts/9', $url);
        self::assertSame(0, $resolver->calls);
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
