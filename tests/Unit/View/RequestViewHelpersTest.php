<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\View;

use Lemonade\Framework\Core\Config\AppConfig;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Routing\UrlGenerator;
use Lemonade\Framework\Session\Contract\SessionInterface;
use Lemonade\Framework\Session\Flash\FlashBagInterface;
use Lemonade\Framework\Session\SessionNamespace;
use Lemonade\Framework\Support\BaseUrlResolver;
use Lemonade\Framework\View\RequestViewHelpers;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class RequestViewHelpersTest extends TestCase
{
    public function testCurrentRequestUrlHelpersUseExplicitRequest(): void
    {
        $helpers = $this->helpers(new ServerRequest('GET', 'https://example.test/articles/15?preview=1'));

        self::assertSame('/articles/15', $helpers->currentPath());
        self::assertSame('preview=1', $helpers->currentQuery());
        self::assertSame('/articles/15?preview=1', $helpers->currentUrl());
        self::assertSame('/articles/15', $helpers->currentUrl(false));
        self::assertSame('https://example.test/articles/15?preview=1', $helpers->currentFullUrl());
        self::assertSame('https://example.test/articles/15', $helpers->currentFullUrl(false));
    }

    public function testRelativeCurrentUrlDoesNotRequireSafeAbsoluteOrigin(): void
    {
        $helpers = new RequestViewHelpers(
            request: new ServerRequest('GET', 'https://attacker.example/articles?preview=1'),
            urlGenerator: new UrlGenerator(new Router()),
        );

        self::assertSame('/articles?preview=1', $helpers->currentUrl());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Absolute request URLs require an explicit base URL');

        $helpers->currentFullUrl();
    }

    public function testUsesCurrentRequestInstanceForEachHelperObject(): void
    {
        $first = $this->helpers(new ServerRequest('GET', 'https://example.test/first'));
        $second = $this->helpers(new ServerRequest('GET', 'https://example.test/second'));

        self::assertSame('/first', $first->currentPath());
        self::assertSame('/second', $second->currentPath());
    }

    public function testActiveUrlAndRouteHelpersUseCurrentRequest(): void
    {
        $router = new Router();
        $router->getNamed('articles.show', '/articles/{id}', \Lemonade\Framework\Routing\ControllerAction::for('ArticleController', 'show'));
        $router->getNamed('articles.index', '/articles', \Lemonade\Framework\Routing\ControllerAction::for('ArticleController', 'index'));

        $helpers = $this->helpers(
            new ServerRequest('GET', 'https://example.test/articles/15?preview=1'),
            router: $router,
        );

        self::assertTrue($helpers->isUrlActive('/articles/15'));
        self::assertFalse($helpers->isUrlActive('/articles'));
        self::assertTrue($helpers->isUrlActive('/articles', startsWith: true));
        self::assertTrue($helpers->isRouteActive('articles.show', ['id' => 15]));
        self::assertFalse($helpers->isRouteActive('articles.index'));
    }

    public function testOldPrefersCanonicalNamespaceOverFlashValue(): void
    {
        $session = new RequestViewHelpersSessionStub();
        $oldInput = new SessionNamespace($session, 'old_input');
        $oldInput->set('email', 'session@example.test');
        $flash = new RequestViewHelpersFlashBagStub([
            'old_input' => ['email' => 'flash@example.test'],
        ]);

        $helpers = $this->helpers(
            new ServerRequest('GET', 'https://example.test/form'),
            flash: $flash,
            session: $session,
        );

        self::assertSame('session@example.test', $helpers->old('email'));
    }

    public function testOldUsesFlashWhenCanonicalNamespaceValueIsMissing(): void
    {
        $session = new RequestViewHelpersSessionStub();
        $oldInput = new SessionNamespace($session, 'old_input');
        $oldInput->set('email', null);
        $flash = new RequestViewHelpersFlashBagStub([
            'old_input' => ['email' => 'flash@example.test'],
        ]);
        $helpers = $this->helpers(
            new ServerRequest('GET', 'https://example.test/form'),
            flash: $flash,
            session: $session,
        );

        self::assertSame('flash@example.test', $helpers->old('email'));
    }

    public function testOldReturnsCallerDefaultAfterEveryStorageFallbackIsMissing(): void
    {
        $helpers = $this->helpers(
            new ServerRequest('GET', 'https://example.test/form'),
            flash: new RequestViewHelpersFlashBagStub(),
            session: new RequestViewHelpersSessionStub(),
        );

        self::assertSame('fallback', $helpers->old('missing', 'fallback'));
    }

    public function testFlashPullsFromExplicitFlashBag(): void
    {
        $flash = new RequestViewHelpersFlashBagStub(['notice' => 'Saved']);
        $helpers = $this->helpers(
            new ServerRequest('GET', 'https://example.test/form'),
            flash: $flash,
        );

        self::assertSame('Saved', $helpers->flash('notice'));
        self::assertSame('fallback', $helpers->flash('notice', 'fallback'));
    }

    private function helpers(
        ServerRequest $request,
        ?Router $router = null,
        ?FlashBagInterface $flash = null,
        ?SessionInterface $session = null,
    ): RequestViewHelpers {
        $router ??= new Router();
        $baseUrl = new BaseUrlResolver(new AppConfig(
            timezone: null,
            baseUrl: 'https://example.test',
            basePath: '',
            publicPath: '',
            env: 'testing',
            debug: false,
            appPath: '',
            configPath: '',
            storagePath: '',
        ));

        return new RequestViewHelpers(
            request: $request,
            urlGenerator: new UrlGenerator($router),
            flash: $flash,
            session: $session,
            absoluteUrl: static fn(string $path): string => $baseUrl->baseUrl($path),
        );
    }
}

final class RequestViewHelpersSessionStub implements SessionInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private array $data = [],
    ) {}

    public function start(): void {}

    public function started(): bool
    {
        return true;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    public function clear(): void
    {
        $this->data = [];
    }

    public function destroy(): void
    {
        $this->data = [];
    }

    public function regenerate(bool $deleteOldSession = true): void
    {
        unset($deleteOldSession);
    }
}

final class RequestViewHelpersFlashBagStub implements FlashBagInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private array $data = [],
    ) {}

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        if (!array_key_exists($key, $this->data)) {
            return $default;
        }

        $value = $this->data[$key];
        unset($this->data[$key]);

        return $value;
    }

    public function all(): array
    {
        return $this->data;
    }

    public function clear(): void
    {
        $this->data = [];
    }
}
