<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Core;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\AbstractController;
use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Http\Request\HttpMethod;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Routing\UrlGenerator;
use Lemonade\Framework\Security\Csrf\CsrfTokenManager;
use Lemonade\Framework\Security\Csrf\CsrfViewHelper;
use Lemonade\Framework\Session\Contract\SessionInterface;
use Lemonade\Framework\Support\BaseUrlResolver;
use Lemonade\Framework\View\View;
use Lemonade\Framework\View\ViewHelpers;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use RuntimeException;

final class ControllerTest extends TestCase
{
    private Psr17Factory $psr17;

    protected function setUp(): void
    {
        $this->psr17 = new Psr17Factory();
    }

    public function testRequestHelpersRequireInitializedControllerContext(): void
    {
        $controller = new ControllerTestSubject();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Controller context is not initialized. Missing ControllerContext.');

        $controller->exposedRequest();
    }

    public function testResponseHelpersRequireInitializedControllerContext(): void
    {
        $controller = new ControllerTestSubject();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Controller context is not initialized. Missing ControllerResponses.');

        $controller->exposedText('Hello');
    }

    public function testRequestContextIsReturnedAfterInitialization(): void
    {
        $request = $this->request();

        $controller = $this->controller($request);

        self::assertSame($request, $controller->exposedRequest());
    }

    public function testRequestInputHelpersReadQueryPostJsonHeadersCookiesServerAndBody(): void
    {
        $request = $this->request(
            method: 'POST',
            uri: '/demo?query_name=Jan',
            headers: [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
                'User-Agent' => 'PHPUnit',
                'Referer' => 'https://example.test/source',
            ],
            body: '{"json_name":"Json Jan","json_int":"42","json_bool":"true"}',
            serverParams: [
                'REMOTE_ADDR' => '127.0.0.1',
                'HTTP_USER_AGENT' => 'PHPUnit',
                'HTTP_REFERER' => 'https://example.test/source',
            ],
        )
            ->withQueryParams([
                'query_name' => 'Jan',
                'page' => '2',
            ])
            ->withParsedBody([
                'post_name' => 'Post Jan',
                'count' => '10',
                'price' => '12.50',
                'enabled' => 'yes',
            ])
            ->withCookieParams([
                'session' => 'abc',
            ]);

        $controller = $this->controller($request);

        self::assertSame('Post Jan', $controller->exposedInput('post_name'));
        self::assertSame('Jan', $controller->exposedQuery('query_name'));
        self::assertSame(['query_name' => 'Jan', 'page' => '2'], $controller->exposedQueryAll());
        self::assertSame('Post Jan', $controller->exposedPost('post_name'));
        self::assertSame([
            'post_name' => 'Post Jan',
            'count' => '10',
            'price' => '12.50',
            'enabled' => 'yes',
        ], $controller->exposedPostAll());

        self::assertSame('application/json', $controller->exposedHeader('Content-Type'));
        self::assertSame('fallback', $controller->exposedHeader('Missing', 'fallback'));
        self::assertArrayHasKey('Content-Type', $controller->exposedHeaders());

        self::assertSame('abc', $controller->exposedCookie('session'));
        self::assertSame(['session' => 'abc'], $controller->exposedCookies());

        self::assertSame('127.0.0.1', $controller->exposedServer('REMOTE_ADDR'));
        self::assertSame('fallback', $controller->exposedServer('MISSING', 'fallback'));
        self::assertSame('127.0.0.1', $controller->exposedServerAll()['REMOTE_ADDR']);

        self::assertSame('{"json_name":"Json Jan","json_int":"42","json_bool":"true"}', $controller->exposedBody());
        self::assertSame('Json Jan', $controller->exposedJsonInput('json_name'));
        self::assertSame([
            'json_name' => 'Json Jan',
            'json_int' => '42',
            'json_bool' => 'true',
        ], $controller->exposedJsonPayload());

        self::assertTrue($controller->exposedIsJsonRequest());
        self::assertTrue($controller->exposedAcceptsJson());
        self::assertTrue($controller->exposedExpectsJson());
        self::assertTrue($controller->exposedIsAjaxRequest());

        self::assertSame('127.0.0.1', $controller->exposedIp());
        self::assertSame('PHPUnit', $controller->exposedUserAgent());
        self::assertSame('https://example.test/source', $controller->exposedReferer());

        self::assertSame('Post Jan', $controller->exposedInputString('post_name'));
        self::assertSame(10, $controller->exposedInputInt('count'));
        self::assertSame(12.5, $controller->exposedInputFloat('price'));
        self::assertTrue($controller->exposedInputBool('enabled'));
    }

    public function testHttpMethodHelpersReflectRequestMethod(): void
    {
        $controller = $this->controller($this->request(method: 'PATCH'));

        self::assertSame('PATCH', $controller->exposedMethod());
        self::assertTrue($controller->exposedIsMethod('PATCH'));
        self::assertTrue($controller->exposedIsMethod(HttpMethod::PATCH));
        self::assertFalse($controller->exposedIsGet());
        self::assertFalse($controller->exposedIsPost());
        self::assertFalse($controller->exposedIsPut());
        self::assertTrue($controller->exposedIsPatch());
        self::assertFalse($controller->exposedIsDelete());
        self::assertFalse($controller->exposedIsHead());
        self::assertFalse($controller->exposedIsOptions());
    }

    public function testResponseHelpersCreateExpectedResponses(): void
    {
        $controller = $this->controller($this->request());

        $text = $controller->exposedText('Plain', 201);
        self::assertSame(201, $text->getStatusCode());
        self::assertSame('text/plain; charset=UTF-8', $text->getHeaderLine('Content-Type'));
        self::assertSame('Plain', (string) $text->getBody());

        $html = $controller->exposedHtml('<strong>HTML</strong>', 202);
        self::assertSame(202, $html->getStatusCode());
        self::assertSame('text/html; charset=UTF-8', $html->getHeaderLine('Content-Type'));
        self::assertSame('<strong>HTML</strong>', (string) $html->getBody());

        $json = $controller->exposedJson(['ok' => true], 203);
        self::assertSame(203, $json->getStatusCode());
        self::assertSame('application/json; charset=UTF-8', $json->getHeaderLine('Content-Type'));
        self::assertSame('{"ok":true}', (string) $json->getBody());

        $redirect = $controller->exposedRedirect('/target', 301);
        self::assertSame(301, $redirect->getStatusCode());
        self::assertSame('/target', $redirect->getHeaderLine('Location'));

        $response = $controller->exposedResponse('Custom', 204, 'text/custom');
        self::assertSame(204, $response->getStatusCode());
        self::assertSame('text/custom', $response->getHeaderLine('Content-Type'));
        self::assertSame('Custom', (string) $response->getBody());
    }

    public function testDownloadHelperCreatesAttachmentResponse(): void
    {
        $filePath = tempnam(sys_get_temp_dir(), 'lemonade-controller-download-');
        self::assertIsString($filePath);

        file_put_contents($filePath, 'download-body');

        try {
            $controller = $this->controller($this->request());

            $response = $controller->exposedDownload(
                filePath: $filePath,
                downloadName: 'example.txt',
                contentType: 'text/plain',
            );

            self::assertSame(200, $response->getStatusCode());
            self::assertSame('text/plain', $response->getHeaderLine('Content-Type'));
            self::assertSame('attachment; filename="example.txt"', $response->getHeaderLine('Content-Disposition'));
            self::assertSame((string) strlen('download-body'), $response->getHeaderLine('Content-Length'));
            self::assertSame('download-body', (string) $response->getBody());
        } finally {
            @unlink($filePath);
        }
    }

    public function testStreamHelperCreatesCallbackStreamResponse(): void
    {
        $controller = $this->controller($this->request());

        $response = $controller->exposedStream(
            static function (): void {
                echo 'stream-body';
            },
            206,
            'text/plain',
            ['X-Test' => 'yes'],
        );

        self::assertSame(206, $response->getStatusCode());
        self::assertSame('text/plain', $response->getHeaderLine('Content-Type'));
        self::assertSame('yes', $response->getHeaderLine('X-Test'));
        self::assertSame('stream-body', (string) $response->getBody());
    }

    public function testAppReturnsApplicationContextFromExplicitContainer(): void
    {
        $context = $this->applicationContext();

        $container = new Container();
        $container->singleton(ApplicationContext::class, $context);

        $controller = $this->controller($this->request(), $container);

        self::assertSame($context, $controller->exposedApp());
        self::assertSame($context, $controller->exposedApp());
    }

    public function testAppThrowsWhenServiceIsNotAvailableInExplicitContainer(): void
    {
        $controller = $this->controller($this->request());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ApplicationContext service is not available.');

        $controller->exposedApp();
    }

    public function testAppHelperRequiresInitializedControllerContext(): void
    {
        $controller = new ControllerTestSubject();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Controller context is not initialized. Missing ControllerServices.');

        $controller->exposedApp();
    }

    public function testViewHelperSharesRequestHelpersForCurrentRender(): void
    {
        $viewsPath = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'lemonade-controller-view-' . uniqid('', true);
        mkdir($viewsPath, 0775, true);
        file_put_contents(
            $viewsPath . DIRECTORY_SEPARATOR . 'current.php',
            '<?= $requestHelpers->currentPath() ?>|<?= $requestHelpers->currentQuery() ?>',
        );

        try {
            $container = new Container();
            $container->singleton(View::class, new View($viewsPath));
            $container->singleton(UrlGenerator::class, new UrlGenerator(new Router()));
            $this->registerViewHelpers($container);

            $controller = $this->controller(
                $this->request(uri: 'https://example.test/current?tab=active'),
                $container,
            );

            self::assertSame('/current|tab=active', $controller->exposedView()->render('current'));
        } finally {
            @unlink($viewsPath . DIRECTORY_SEPARATOR . 'current.php');
            @rmdir($viewsPath);
        }
    }

    public function testViewHelperRefreshesRequestHelpersForNextRequest(): void
    {
        $viewsPath = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'lemonade-controller-view-' . uniqid('', true);
        mkdir($viewsPath, 0775, true);
        file_put_contents($viewsPath . DIRECTORY_SEPARATOR . 'current.php', '<?= $requestHelpers->currentPath() ?>');

        try {
            $container = new Container();
            $container->singleton(View::class, new View($viewsPath));
            $container->singleton(UrlGenerator::class, new UrlGenerator(new Router()));
            $this->registerViewHelpers($container);
            $controller = new ControllerTestSubject();

            $controller->setControllerContext(
                $this->request(uri: 'https://example.test/first'),
                $this->responseFactory(),
                $this->streamFactory(),
                $container,
            );
            self::assertSame('/first', $controller->exposedView()->render('current'));

            $controller->setControllerContext(
                $this->request(uri: 'https://example.test/second'),
                $this->responseFactory(),
                $this->streamFactory(),
                $container,
            );
            self::assertSame('/second', $controller->exposedView()->render('current'));
        } finally {
            @unlink($viewsPath . DIRECTORY_SEPARATOR . 'current.php');
            @rmdir($viewsPath);
        }
    }

    public function testSetControllerContextResetsRequestDataAndResponseBuilder(): void
    {
        $controller = $this->controller(
            $this->request(method: 'GET')->withQueryParams(['value' => 'first']),
        );

        self::assertSame('first', $controller->exposedQuery('value'));

        $controller->setControllerContext(
            $this->request(method: 'GET')->withQueryParams(['value' => 'second']),
            $this->psr17,
            $this->psr17,
            new Container(),
        );

        self::assertSame('second', $controller->exposedQuery('value'));

        $response = $controller->exposedText('After reset');
        self::assertSame('After reset', (string) $response->getBody());
    }

    private function controller(ServerRequestInterface $request, ?ContainerInterface $container = null): ControllerTestSubject
    {
        $controller = new ControllerTestSubject();

        $controller->setControllerContext(
            $request,
            $this->responseFactory(),
            $this->streamFactory(),
            $container ?? new Container(),
        );

        return $controller;
    }

    /**
     * @param array<string, string|string[]> $headers
     * @param array<string, mixed> $serverParams
     */
    private function request(
        string $method = 'GET',
        string $uri = '/demo',
        array $headers = [],
        string $body = '',
        array $serverParams = [],
    ): ServerRequestInterface {
        $request = new ServerRequest($method, $uri, $headers, $body, '1.1', $serverParams);

        if ($body !== '') {
            $request = $request->withBody($this->psr17->createStream($body));
        }

        return $request;
    }

    private function applicationContext(): ApplicationContext
    {
        return new ApplicationContext(
            Environment::Testing,
            new Path(sys_get_temp_dir()),
            DebugMode::disabled(),
        );
    }

    private function responseFactory(): ResponseFactoryInterface
    {
        return $this->psr17;
    }

    private function streamFactory(): StreamFactoryInterface
    {
        return $this->psr17;
    }

    private function registerViewHelpers(Container $container): void
    {
        $config = new Config(['app' => ['base_url' => 'https://example.test']]);
        $session = new ControllerTestSession();
        $csrf = new CsrfViewHelper(new CsrfTokenManager($session));

        $container->singleton(Config::class, $config);
        $container->singleton(BaseUrlResolver::class, static fn(): BaseUrlResolver => new BaseUrlResolver($config));
        $container->singleton(CsrfViewHelper::class, $csrf);
        $container->singleton(TranslatorInterface::class, new ControllerTestTranslator());
        $container->singleton(ViewHelpers::class, static fn(ContainerInterface $container): ViewHelpers => new ViewHelpers(
            baseUrl: $container->get(BaseUrlResolver::class),
            urlGenerator: $container->get(UrlGenerator::class),
            csrf: $container->get(CsrfViewHelper::class),
            translator: $container->get(TranslatorInterface::class),
            config: $container->get(Config::class),
        ));
    }
}

final class ControllerTestSession implements SessionInterface
{
    /** @var array<string, mixed> */
    private array $values = [];

    public function start(): void {}

    public function started(): bool
    {
        return true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->values[$key] = $value;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    public function remove(string $key): void
    {
        unset($this->values[$key]);
    }

    public function clear(): void
    {
        $this->values = [];
    }

    public function regenerate(bool $deleteOldSession = true): void
    {
        unset($deleteOldSession);
    }
}

final class ControllerTestTranslator implements TranslatorInterface
{
    public function setLocale(?string $locale): self
    {
        unset($locale);

        return $this;
    }

    public function locale(): ?string
    {
        return null;
    }

    public function get(string $key, array $replacements = [], ?string $locale = null): string
    {
        unset($replacements, $locale);

        return $key;
    }

    public function group(string $group, ?string $locale = null): array
    {
        unset($group, $locale);

        return [];
    }

    public function all(?string $locale = null): array
    {
        unset($locale);

        return [];
    }
}

final class ControllerTestSubject extends AbstractController
{
    public function exposedRequest(): ServerRequestInterface
    {
        return $this->request();
    }

    public function exposedView(): View
    {
        return $this->view();
    }

    public function exposedInput(string $key, mixed $default = null): mixed
    {
        return $this->input($key, $default);
    }

    public function exposedQuery(string $key, mixed $default = null): mixed
    {
        return $this->query($key, $default);
    }

    /**
     * @return array<string, mixed>
     */
    public function exposedQueryAll(): array
    {
        return $this->queryAll();
    }

    public function exposedPost(string $key, mixed $default = null): mixed
    {
        return $this->post($key, $default);
    }

    /**
     * @return array<string, mixed>
     */
    public function exposedPostAll(): array
    {
        return $this->postAll();
    }

    public function exposedHeader(string $name, ?string $default = null): ?string
    {
        return $this->header($name, $default);
    }

    /**
     * @return array<string, string[]>
     */
    public function exposedHeaders(): array
    {
        return $this->headers();
    }

    public function exposedCookie(string $name, mixed $default = null): mixed
    {
        return $this->cookie($name, $default);
    }

    /**
     * @return array<string, mixed>
     */
    public function exposedCookies(): array
    {
        return $this->cookies();
    }

    public function exposedServer(string $key, mixed $default = null): mixed
    {
        return $this->server($key, $default);
    }

    /**
     * @return array<string, mixed>
     */
    public function exposedServerAll(): array
    {
        return $this->serverAll();
    }

    public function exposedBody(): string
    {
        return $this->body();
    }

    public function exposedJsonInput(string $key, mixed $default = null): mixed
    {
        return $this->jsonInput($key, $default);
    }

    /**
     * @return array<string, mixed>
     */
    public function exposedJsonPayload(): array
    {
        return $this->jsonPayload();
    }

    public function exposedIsJsonRequest(): bool
    {
        return $this->isJsonRequest();
    }

    public function exposedAcceptsJson(): bool
    {
        return $this->acceptsJson();
    }

    public function exposedExpectsJson(): bool
    {
        return $this->expectsJson();
    }

    public function exposedText(string $content, int $status = 200): ResponseInterface
    {
        return $this->text($content, $status);
    }

    public function exposedHtml(string $content, int $status = 200): ResponseInterface
    {
        return $this->html($content, $status);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function exposedJson(array $payload, int $status = 200): ResponseInterface
    {
        return $this->json($payload, $status);
    }

    public function exposedRedirect(string $to, int $status = 302): ResponseInterface
    {
        return $this->redirect($to, $status);
    }

    public function exposedDownload(
        string $filePath,
        ?string $downloadName = null,
        string $contentType = 'application/octet-stream',
    ): ResponseInterface {
        return $this->download($filePath, $downloadName, $contentType);
    }

    public function exposedResponse(
        string $content = '',
        int $status = 200,
        string $contentType = 'text/html; charset=UTF-8',
    ): ResponseInterface {
        return $this->response($content, $status, $contentType);
    }

    /**
     * @param callable():void $producer
     * @param array<string, string> $headers
     */
    public function exposedStream(
        callable $producer,
        int $status = 200,
        string $contentType = 'text/plain; charset=UTF-8',
        array $headers = [],
    ): ResponseInterface {
        return $this->stream($producer, $status, $contentType, $headers);
    }

    public function exposedIsAjaxRequest(): bool
    {
        return $this->isAjaxRequest();
    }

    public function exposedIp(): ?string
    {
        return $this->ip();
    }

    public function exposedUserAgent(?string $default = null): ?string
    {
        return $this->userAgent($default);
    }

    public function exposedReferer(?string $default = null): ?string
    {
        return $this->referer($default);
    }

    public function exposedMethod(): string
    {
        return $this->method();
    }

    public function exposedIsMethod(HttpMethod|string $method): bool
    {
        return $this->isMethod($method);
    }

    public function exposedIsGet(): bool
    {
        return $this->isGet();
    }

    public function exposedIsPost(): bool
    {
        return $this->isPost();
    }

    public function exposedIsPut(): bool
    {
        return $this->isPut();
    }

    public function exposedIsPatch(): bool
    {
        return $this->isPatch();
    }

    public function exposedIsDelete(): bool
    {
        return $this->isDelete();
    }

    public function exposedIsHead(): bool
    {
        return $this->isHead();
    }

    public function exposedIsOptions(): bool
    {
        return $this->isOptions();
    }

    public function exposedInputString(string $key, string $default = ''): string
    {
        return $this->inputString($key, $default);
    }

    public function exposedInputInt(string $key, int $default = 0): int
    {
        return $this->inputInt($key, $default);
    }

    public function exposedInputFloat(string $key, float $default = 0.0): float
    {
        return $this->inputFloat($key, $default);
    }

    public function exposedInputBool(string $key, bool $default = false): bool
    {
        return $this->inputBool($key, $default);
    }

    public function exposedApp(): ApplicationContext
    {
        return $this->app();
    }

}
