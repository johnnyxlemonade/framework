<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\View;

use Lemonade\Framework\Component\ComponentRegistry;
use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\Exception\ScopedServiceRequestedFromRootException;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Container\ScopeKind;
use Lemonade\Framework\Core\Config\AppConfig;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Core\Framework;
use Lemonade\Framework\Core\Health\FrameworkHealthFastPath;
use Lemonade\Framework\Core\Kernel;
use Lemonade\Framework\Http\Psr\ResponseEmitter;
use Lemonade\Framework\Image\Contract\ImageUrlResolverInterface;
use Lemonade\Framework\Image\ImageViewHelper;
use Lemonade\Framework\Image\Value\ImageDimensions;
use Lemonade\Framework\Image\Value\ImageFormat;
use Lemonade\Framework\Image\Value\ImageReference;
use Lemonade\Framework\Observability\Benchmark\Benchmark;
use Lemonade\Framework\Localization\Config\LocalizationConfig;
use Lemonade\Framework\Localization\Config\LocalizationUrlConfig;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Routing\ControllerAction;
use Lemonade\Framework\Routing\LocaleUrlStrategyInterface;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Routing\UrlGenerator;
use Lemonade\Framework\Security\Csrf\CsrfTokenManager;
use Lemonade\Framework\Security\Csrf\CsrfViewHelper;
use Lemonade\Framework\Session\Contract\SessionInterface;
use Lemonade\Framework\Support\BaseUrlResolver;
use Lemonade\Framework\View\Config\ViewConfigDefinition;
use Lemonade\Framework\View\Config\ViewConfigResolver;
use Lemonade\Framework\View\RequestViewHelpers;
use Lemonade\Framework\View\View;
use Lemonade\Framework\View\ViewRendererInterface;
use Lemonade\Framework\View\ViewResourceRegistry;
use Lemonade\Framework\View\ViewHelpers;
use Lemonade\Framework\View\ViewServiceProvider;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use LogicException;
use RuntimeException;

final class ViewServiceProviderTest extends TestCase
{
    private string $root = '';
    private string $viewsPath = '';

    protected function setUp(): void
    {
        $this->root = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'lemonade-view-provider-' . uniqid('', true);
        $this->viewsPath = $this->root . DIRECTORY_SEPARATOR . 'views';
    }

    protected function tearDown(): void
    {
        $this->deleteRecursive($this->root);
    }

    public function testRegisterBindsViewSingletonWithApplicationScopedHelpers(): void
    {
        $container = $this->buildContainer($this->viewsPath, 'https://example.test');
        $provider = new ViewServiceProvider();
        $provider->register($container);

        self::assertTrue($container->isBound(View::class));
        self::assertTrue($container->isBound(ViewResourceRegistry::class));
        self::assertTrue($container->isBound(RequestViewHelpers::class));

        $viewA = $container->get(View::class);
        $viewB = $container->get(View::class);
        self::assertSame($viewA, $viewB);

        $this->writeView('helper', '<?= get_class($component) ?>|<?= get_class($url) ?>');
        $output = $viewA->render('helper');

        self::assertStringContainsString(ComponentRegistry::class, $output);
        self::assertStringContainsString(UrlGenerator::class, $output);
    }

    public function testRegisterProvidesSharedViewResourceRegistryToView(): void
    {
        $container = $this->buildContainer($this->viewsPath, 'https://example.test');
        (new ViewServiceProvider())->register($container);

        $moduleViews = $this->root . DIRECTORY_SEPARATOR . 'module-views';
        mkdir($moduleViews, 0775, true);
        file_put_contents($moduleViews . DIRECTORY_SEPARATOR . 'editor.php', 'EDITOR');

        $container->get(ViewResourceRegistry::class)->register('users', $moduleViews);

        self::assertSame('EDITOR', $container->get(View::class)->render('users::editor'));
    }

    public function testRegisterSharesHelpersVariableIntoRenderedView(): void
    {
        $container = $this->rendererContainer();

        $this->writeView('helpers-variable', '<?= $helpers instanceof ' . '\\' . ViewHelpers::class . ' ? $helpers->asset("css/app.css") : "missing" ?>');
        $scope = $container->beginScope(ScopeKind::Request);

        try {
            $scope->bindScopedInstance(
                \Psr\Http\Message\ServerRequestInterface::class,
                new ServerRequest('GET', '/helpers'),
            );

            self::assertSame(
                '/css/app.css',
                $scope->get(ViewRendererInterface::class)->content('helpers-variable'),
            );
        } finally {
            $scope->close();
        }
    }

    public function testRequestViewDelegatesEveryViewHelperAndKeepsLegacyHelpersVariable(): void
    {
        $container = $this->buildContainer($this->viewsPath, 'https://example.test');
        $router = new Router();
        $router->localizedGroup(static function (Router $router): void {
            $router->getNamed(
                'article.index',
                '/articles',
                ControllerAction::for('ArticleController', 'index'),
            );
            $router->getNamed(
                'article.show',
                '/articles/{id}',
                ControllerAction::for('ArticleController', 'show'),
            );
        });
        $container->singleton(
            UrlGenerator::class,
            new UrlGenerator($router, null, new ViewServiceProviderLocaleUrlStrategyStub()),
        );
        $container->scoped(
            TranslatorInterface::class,
            static fn(): TranslatorInterface => new ViewServiceProviderTranslatorStub('cs'),
        );
        $container->singleton(
            ImageViewHelper::class,
            new ImageViewHelper(new ViewServiceProviderImageUrlResolverStub()),
        );
        $factory = new Psr17Factory();
        $container->singleton(ResponseFactoryInterface::class, $factory);
        $container->singleton(StreamFactoryInterface::class, $factory);
        (new ViewServiceProvider())->register($container);

        $this->writeView(
            'delegates',
            <<<'PHP'
<?php $token = $this->csrfToken('profile'); ?>
<?= $this->lang('messages.hello', ['name' => 'Jan']) ?>|
<?= $this->lang('messages.hello', ['name' => 'Jane'], 'en') ?>|
<?= $this->lang('messages.missing') ?>|
<?= json_encode($this->langGroup('messages', 'cs'), JSON_THROW_ON_ERROR) ?>|
<?= json_encode($this->langAll('en'), JSON_THROW_ON_ERROR) ?>|
<?= $this->currentLocale() ?>|
<?= $this->currentPath() ?>|
<?= $this->currentQuery() ?>|
<?= $this->currentUrl() ?>|
<?= $this->currentUrl(false) ?>|
<?= $this->currentFullUrl() ?>|
<?= $this->currentFullUrl(false) ?>|
<?= $this->isUrlActive('/articles/7') ? 'url-exact' : 'url-missing' ?>|
<?= $this->isUrlActive('/articles', true) ? 'url-prefix' : 'url-prefix-missing' ?>|
<?= $this->isRouteActive('article.show', ['id' => 7]) ? 'route-active' : 'route-missing' ?>|
<?= $this->isRouteActive('article.index', startsWith: true) ? 'route-prefix' : 'route-prefix-missing' ?>|
<?= $this->url('article.show', ['id' => 7]) ?>|
<?= $this->localizedUrl('article.show', ['id' => 7], 'cs') ?>|
<?= $this->asset('css/app.css') ?>|
<?= $this->image($image, new \Lemonade\Framework\Image\ImageViewOptions(alt: 'Cover')) ?>|
<?= $token ?>|
<?= $this->csrfField('profile') ?>|
<?= $helpers === $boundHelpers ? 'legacy' : 'missing' ?>|
<?= $requestHelpers->currentPath() === $this->currentPath() ? 'request-legacy' : 'request-missing' ?>
PHP,
        );

        $scope = $container->beginScope(ScopeKind::Request);

        try {
            $scope->bindScopedInstance(
                \Psr\Http\Message\ServerRequestInterface::class,
                new ServerRequest('GET', 'https://attacker.example/articles/7?preview=1'),
            );
            $helpers = $scope->get(ViewHelpers::class);
            $image = new ImageReference(
                'catalog/cover.webp',
                new ImageDimensions(640, 480),
                ImageFormat::Webp,
            );
            $output = $scope->get(ViewRendererInterface::class)->content(
                'delegates',
                ['image' => $image, 'boundHelpers' => $helpers],
            );

            self::assertStringContainsString('Ahoj Jan|', $output);
            self::assertStringContainsString('Hello Jane|', $output);
            self::assertStringContainsString('messages.missing|', $output);
            self::assertStringContainsString('{"hello":"Ahoj {name}"}|', $output);
            self::assertStringContainsString('{"messages":{"hello":"Hello {name}"}}|', $output);
            self::assertStringContainsString('cs|', $output);
            self::assertStringContainsString('/articles/7|', $output);
            self::assertStringContainsString('preview=1|', $output);
            self::assertStringContainsString('/articles/7?preview=1|', $output);
            self::assertStringContainsString('https://example.test/articles/7?preview=1|', $output);
            self::assertStringContainsString('https://example.test/articles/7|', $output);
            self::assertStringContainsString('url-exact|', $output);
            self::assertStringContainsString('url-prefix|', $output);
            self::assertStringContainsString('route-active|', $output);
            self::assertStringContainsString('route-prefix|', $output);
            self::assertStringContainsString('/cs/articles/7|', $output);
            self::assertStringContainsString('/css/app.css|', $output);
            self::assertStringContainsString(
                '<img src="/media/catalog/cover.webp" alt="Cover" loading="lazy" width="640" height="480" decoding="async">|',
                $output,
            );
            self::assertStringContainsString('name="LEMONADE_CSRF"', $output);
            self::assertStringContainsString('value="' . $this->csrfTokenFromOutput($output) . '"', $output);
            self::assertStringContainsString('legacy|', $output);
            self::assertStringEndsWith('request-legacy', $output);
        } finally {
            $scope->close();
        }
    }

    public function testLayoutAndPartialUseTheSameRequestHelperContext(): void
    {
        $container = $this->rendererContainerWithScopedLocale();
        $this->writeView(
            'layouts.context',
            '[<?= $this->currentLocale() ?>|<?= $this->content() ?>]',
        );
        $this->writeView(
            'partials.context',
            '<?= $this->currentLocale() ?>:<?= $token === $this->csrfToken("shared") ? "same" : "different" ?>',
        );
        $this->writeView(
            'pages.context',
            '<?php $this->extend("layouts.context"); $token = $this->csrfToken("shared"); ?><?= $this->partial("partials.context", ["token" => $token]) ?>',
        );
        $scope = $container->beginScope(ScopeKind::Request);

        try {
            $scope->bindScopedInstance(
                \Psr\Http\Message\ServerRequestInterface::class,
                new ServerRequest('GET', '/cs/context'),
            );

            self::assertSame(
                '[cs|cs:same]',
                $scope->get(ViewRendererInterface::class)->content('pages.context'),
            );
        } finally {
            $scope->close();
        }
    }

    public function testRequestViewsKeepLocaleSessionAndCsrfStateIsolated(): void
    {
        $container = $this->rendererContainerWithScopedLocale();
        $this->writeView('request-context', '<?= $this->currentLocale() ?>|<?= $this->csrfToken("login") ?>');
        $firstScope = $container->beginScope(ScopeKind::Request);
        $firstScope->bindScopedInstance(
            \Psr\Http\Message\ServerRequestInterface::class,
            new ServerRequest('GET', '/cs/account'),
        );
        $secondScope = $container->beginScope(ScopeKind::Request);
        $secondScope->bindScopedInstance(
            \Psr\Http\Message\ServerRequestInterface::class,
            new ServerRequest('GET', '/en/account'),
        );

        try {
            [$firstLocale, $firstToken] = explode(
                '|',
                $firstScope->get(ViewRendererInterface::class)->content('request-context'),
                2,
            );
            [$secondLocale, $secondToken] = explode(
                '|',
                $secondScope->get(ViewRendererInterface::class)->content('request-context'),
                2,
            );

            self::assertSame('cs', $firstLocale);
            self::assertSame('en', $secondLocale);
            self::assertNotSame('', $firstToken);
            self::assertNotSame('', $secondToken);
            self::assertNotSame($firstToken, $secondToken);
        } finally {
            $firstScope->close();
            $secondScope->close();
        }
    }

    public function testCloningRequestViewDiscardsHelpersAndRequestSharedData(): void
    {
        $container = $this->rendererContainer();
        $this->writeView('request-marker', '<?= $requestMarker ?? "missing" ?>');
        $scope = $container->beginScope(ScopeKind::Request);

        try {
            $scope->bindScopedInstance(
                \Psr\Http\Message\ServerRequestInterface::class,
                new ServerRequest('GET', '/clone'),
            );
            $requestView = $container->get(View::class)->forRequest(
                $scope->get(ViewHelpers::class),
                $scope->get(RequestViewHelpers::class),
                ['requestMarker' => 'previous-request'],
            );
            $clone = clone $requestView;

            self::assertSame('missing', $clone->render('request-marker'));

            $this->expectException(LogicException::class);
            $this->expectExceptionMessage('Request view helpers are not bound.');
            $clone->currentPath();
        } finally {
            $scope->close();
        }
    }

    public function testRegisterResolvesRelativeConfiguredBasePathAgainstApplicationBasePath(): void
    {
        $relativeViewsPath = 'app/Views';
        $container = $this->buildContainer($relativeViewsPath, 'https://example.test');
        $provider = new ViewServiceProvider();
        $provider->register($container);

        $this->writeProjectView('app/Views', 'frontend.home.index', 'RELATIVE');

        $cwd = getcwd();
        $publicDir = $this->root . DIRECTORY_SEPARATOR . 'public';
        if (!is_dir($publicDir)) {
            mkdir($publicDir, 0775, true);
        }
        if (is_string($cwd)) {
            chdir($publicDir);
        }
        try {
            $output = $container->get(View::class)->render('frontend.home.index');
        } finally {
            if (is_string($cwd)) {
                chdir($cwd);
            }
        }

        self::assertSame('RELATIVE', $output);
    }

    public function testRegisterUsesDefaultBasePathWhenViewConfigIsNotBound(): void
    {
        $container = $this->buildContainer(null, 'https://example.test');
        $provider = new ViewServiceProvider();
        $provider->register($container);

        $this->writeProjectView('app/Views', 'fallback', 'FALLBACK');

        $cwd = getcwd();
        $publicDir = $this->root . DIRECTORY_SEPARATOR . 'public';
        if (!is_dir($publicDir)) {
            mkdir($publicDir, 0775, true);
        }
        if (is_string($cwd)) {
            chdir($publicDir);
        }
        try {
            $output = $container->get(View::class)->render('fallback');
        } finally {
            if (is_string($cwd)) {
                chdir($cwd);
            }
        }

        self::assertSame('FALLBACK', $output);
    }

    public function testRegisterKeepsAbsoluteConfiguredBasePath(): void
    {
        $container = $this->buildContainer($this->viewsPath, 'https://example.test');
        $provider = new ViewServiceProvider();
        $provider->register($container);

        $this->writeView('absolute', 'ABSOLUTE');

        $cwd = getcwd();
        $publicDir = $this->root . DIRECTORY_SEPARATOR . 'public';
        if (!is_dir($publicDir)) {
            mkdir($publicDir, 0775, true);
        }
        if (is_string($cwd)) {
            chdir($publicDir);
        }
        try {
            $output = $container->get(View::class)->render('absolute');
        } finally {
            if (is_string($cwd)) {
                chdir($cwd);
            }
        }

        self::assertSame('ABSOLUTE', $output);
    }

    public function testRegisterDoesNotInstantiateViewServices(): void
    {
        $container = $this->buildContainer($this->viewsPath, 'https://example.test');

        (new ViewServiceProvider())->register($container);

        self::assertFalse($this->hasContainerInstance($container, ViewConfigResolver::class));
        self::assertFalse($this->hasContainerInstance($container, \Lemonade\Framework\View\Config\ViewConfig::class));
        self::assertFalse($this->hasContainerInstance($container, ViewHelpers::class));
        self::assertFalse($this->hasContainerInstance($container, RequestViewHelpers::class));
        self::assertFalse($this->hasContainerInstance($container, View::class));
    }

    public function testFirstViewResolutionBuildsViewGraphLazily(): void
    {
        $container = $this->buildContainer($this->viewsPath, 'https://example.test');
        (new ViewServiceProvider())->register($container);

        self::assertFalse($this->hasContainerInstance($container, View::class));

        $view = $container->get(View::class);

        self::assertInstanceOf(View::class, $view);
        self::assertTrue($this->hasContainerInstance($container, ViewConfigResolver::class));
        self::assertTrue($this->hasContainerInstance($container, \Lemonade\Framework\View\Config\ViewConfig::class));
        self::assertFalse($this->hasContainerInstance($container, ViewHelpers::class));
        self::assertFalse($this->hasContainerInstance($container, RequestViewHelpers::class));
        self::assertTrue($this->hasContainerInstance($container, View::class));
    }

    public function testViewAndHelpersRespectApplicationAndRequestLifecycles(): void
    {
        $container = $this->buildContainer($this->viewsPath, 'https://example.test');
        (new ViewServiceProvider())->register($container);

        $viewA = $container->get(View::class);
        $viewB = $container->get(View::class);
        $firstScope = $container->beginScope(ScopeKind::Request);
        $secondScope = $container->beginScope(ScopeKind::Request);

        try {
            $firstScope->bindScopedInstance(
                \Psr\Http\Message\ServerRequestInterface::class,
                new ServerRequest('GET', '/first'),
            );
            $secondScope->bindScopedInstance(
                \Psr\Http\Message\ServerRequestInterface::class,
                new ServerRequest('GET', '/second'),
            );
            $helpersA = $firstScope->get(ViewHelpers::class);
            $helpersB = $secondScope->get(ViewHelpers::class);
            $requestHelpersA = $firstScope->get(RequestViewHelpers::class);
            $requestHelpersB = $secondScope->get(RequestViewHelpers::class);

            self::assertSame($viewA, $viewB);
            self::assertSame($helpersA, $firstScope->get(ViewHelpers::class));
            self::assertNotSame($helpersA, $helpersB);
            self::assertSame($requestHelpersA, $firstScope->get(RequestViewHelpers::class));
            self::assertNotSame($requestHelpersA, $requestHelpersB);
            self::assertSame('/first', $requestHelpersA->currentPath());
            self::assertSame('/second', $requestHelpersB->currentPath());
        } finally {
            $firstScope->close();
            $secondScope->close();
        }
    }

    public function testInvalidConfiguredBasePathFailsConsistentlyOnFirstRender(): void
    {
        $container = $this->buildContainer(
            $this->root . DIRECTORY_SEPARATOR . 'missing-views',
            'https://example.test',
        );
        (new ViewServiceProvider())->register($container);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('View not found:');

        $container->get(View::class)->render('missing');
    }

    public function testHealthFlowDoesNotInitializeViewLayer(): void
    {
        $kernel = $this->kernelWithDefaultConfig();
        $response = $kernel->run(new ServerRequest('GET', '/api/framework/health'));
        $container = $kernel->container();

        self::assertSame(200, $response->getStatusCode());
        self::assertFalse($this->hasContainerInstance($container, ViewConfigResolver::class));
        self::assertFalse($this->hasContainerInstance($container, \Lemonade\Framework\View\Config\ViewConfig::class));
        self::assertFalse($this->hasContainerInstance($container, ViewHelpers::class));
        self::assertFalse($this->hasContainerInstance($container, RequestViewHelpers::class));
        self::assertFalse($this->hasContainerInstance($container, View::class));
    }

    public function testViewRendererCannotBeResolvedFromRootContainer(): void
    {
        $container = $this->rendererContainer();

        $this->expectException(ScopedServiceRequestedFromRootException::class);
        $container->get(ViewRendererInterface::class);
    }

    public function testViewRendererRendersHtmlResponseAndPreservesTemplateViewContract(): void
    {
        $this->writeView('layouts.main', '[<?= $this instanceof \\Lemonade\\Framework\\View\\View ? "view" : "other" ?>|<?= $this->section("head") ?>|<?= $this->content() ?>]');
        $this->writeView('partials.item', 'PARTIAL');
        $this->writeView(
            'pages.child',
            '<?php $this->extend("layouts.main"); $this->start("head"); ?>HEAD<?php $this->end(); ?>CONTENT:<?= $this->partial("partials.item") ?>',
        );
        $this->writeView('plain', 'PLAIN');
        $container = $this->rendererContainer();
        $scope = $container->beginScope(ScopeKind::Request);

        try {
            $scope->bindScopedInstance(\Psr\Http\Message\ServerRequestInterface::class, new ServerRequest('GET', '/renderer'));
            $renderer = $scope->get(ViewRendererInterface::class);

            self::assertSame($renderer, $scope->get(ViewRendererInterface::class));
            self::assertSame('PLAIN', $renderer->content('plain'));

            $response = $renderer->render('pages.child', status: 202);

            self::assertSame(202, $response->getStatusCode());
            self::assertSame('text/html; charset=UTF-8', $response->getHeaderLine('Content-Type'));
            self::assertSame('[view|HEAD|CONTENT:PARTIAL]', (string) $response->getBody());
        } finally {
            $scope->close();
        }
    }

    public function testViewRenderersDoNotShareRequestDataOrLegacyTemporaryData(): void
    {
        $this->writeView('current', '<?= $this->currentPath() ?>|<?= $requestHelpers->currentPath() ?>|<?= $temporary ?? "missing" ?>');
        $container = $this->rendererContainer();
        $container->get(View::class)->shareOnce('temporary', 'root-only');

        $firstScope = $container->beginScope(ScopeKind::Request);
        $firstScope->bindScopedInstance(\Psr\Http\Message\ServerRequestInterface::class, new ServerRequest('GET', '/first'));
        $secondScope = $container->beginScope(ScopeKind::Request);
        $secondScope->bindScopedInstance(\Psr\Http\Message\ServerRequestInterface::class, new ServerRequest('GET', '/second'));

        try {
            $firstRenderer = $firstScope->get(ViewRendererInterface::class);
            $secondRenderer = $secondScope->get(ViewRendererInterface::class);

            self::assertNotSame($firstRenderer, $secondRenderer);
            self::assertSame('/first|/first|missing', $firstRenderer->content('current'));
            self::assertSame('/second|/second|missing', $secondRenderer->content('current'));
        } finally {
            $firstScope->close();
            $secondScope->close();
        }
    }

    private function buildContainer(?string $viewBasePath, string $baseUrl): Container
    {
        $container = new Container();
        $container->singleton(
            ApplicationContext::class,
            new ApplicationContext(
                Environment::Testing,
                new Path($this->root),
                DebugMode::disabled(),
            ),
        );
        $container->singleton(ComponentRegistry::class, new ComponentRegistry($container));
        $registry = new ConfigDefinitionRegistry();
        if ($viewBasePath !== null) {
            $registry->addDefinition(ViewConfigDefinition::create()->basePath($viewBasePath));
        }
        $container->singleton(ConfigDefinitionRegistry::class, $registry);
        $container->singleton(
            BaseUrlResolver::class,
            static fn(): BaseUrlResolver => new BaseUrlResolver(
                new AppConfig(null, $baseUrl, '', '', 'testing', false, '', '', ''),
            ),
        );
        $container->singleton(UrlGenerator::class, new UrlGenerator(new Router()));
        $container->scoped(SessionInterface::class, InMemorySession::class);
        $container->scoped(CsrfTokenManager::class, CsrfTokenManager::class);
        $container->scoped(CsrfViewHelper::class, CsrfViewHelper::class);
        $container->singleton(TranslatorInterface::class, new ViewServiceProviderTranslatorStub());
        $container->singleton(LocalizationConfig::class, new LocalizationConfig('en', 'en', ['en'], new LocalizationUrlConfig(false, 'localized.', '/{locale}', 'locale', false)));

        return $container;
    }

    private function rendererContainer(): Container
    {
        $container = $this->buildContainer($this->viewsPath, 'https://example.test');
        $factory = new Psr17Factory();
        $container->singleton(ResponseFactoryInterface::class, $factory);
        $container->singleton(StreamFactoryInterface::class, $factory);
        (new ViewServiceProvider())->register($container);

        return $container;
    }

    private function rendererContainerWithScopedLocale(): Container
    {
        $container = $this->buildContainer($this->viewsPath, 'https://example.test');
        $container->scoped(
            TranslatorInterface::class,
            static function (ContainerInterface $container): TranslatorInterface {
                $path = $container->get(\Psr\Http\Message\ServerRequestInterface::class)
                    ->getUri()
                    ->getPath();

                return new ViewServiceProviderTranslatorStub(
                    str_starts_with($path, '/cs/') ? 'cs' : 'en',
                );
            },
        );
        $factory = new Psr17Factory();
        $container->singleton(ResponseFactoryInterface::class, $factory);
        $container->singleton(StreamFactoryInterface::class, $factory);
        (new ViewServiceProvider())->register($container);

        return $container;
    }

    private function csrfTokenFromOutput(string $output): string
    {
        if (preg_match('/\|\s*([a-f0-9]{64})\|\s*<input/', $output, $matches) !== 1) {
            self::fail('Rendered view did not contain the expected CSRF token and field sequence.');
        }

        return $matches[1];
    }

    private function kernelWithDefaultConfig(): Kernel
    {
        $this->writeKernelDefaultConfigFiles();

        $context = new ApplicationContext(
            Environment::Testing,
            new Path($this->root),
            DebugMode::disabled(),
        );
        $container = new Container();
        $framework = new Framework($container, $context);

        return new Kernel(
            $context,
            $container,
            $framework,
            new ResponseEmitter(),
            new FrameworkHealthFastPath(
                $container->get(ConfigDefinitionRegistry::class),
                $container->get(Benchmark::class),
                $context,
            ),
            $container->get(Benchmark::class),
        );
    }

    private function writeKernelDefaultConfigFiles(): void
    {
        $configDir = $this->root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Config';
        if (!is_dir($configDir)) {
            mkdir($configDir, 0775, true);
        }

        $this->writeKernelConfigFile(
            'Config.yaml',
            "shared:\n  - App\n  - Api\nhttp: []\ncli:\n  - Commands\n",
        );
        $this->writeKernelConfigFile('App.yaml', "module: app\nconfig: {}\n");
        $this->writeKernelConfigFile('Api.yaml', "module: api\nconfig: {}\n");
        $this->writeKernelConfigFile('Commands.yaml', "module: commands\nconfig:\n  commands: []\n");
        $this->writeKernelConfigFile(
            'Routing.php',
            "<?php\n\ndeclare(strict_types=1);\n\nuse Lemonade\\Framework\\Routing\\Router;\n\nreturn static function (Router \$router): void {\n};\n",
        );
    }

    private function writeKernelConfigFile(string $file, string $contents): void
    {
        $path = $this->root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . $file;
        file_put_contents($path, $contents);
    }

    private function writeProjectView(string $relativeBasePath, string $name, string $contents): void
    {
        $path = $this->root
            . DIRECTORY_SEPARATOR
            . trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativeBasePath), '/\\')
            . DIRECTORY_SEPARATOR
            . str_replace('.', DIRECTORY_SEPARATOR, $name)
            . '.php';
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents($path, $contents);
    }

    private function hasContainerInstance(ContainerInterface $container, string $id): bool
    {
        $reflection = new \ReflectionObject($container);
        $property = $reflection->getProperty('instances');
        /** @var array<string, mixed> $instances */
        $instances = $property->getValue($container);

        return array_key_exists($id, $instances);
    }

    private function writeView(string $name, string $contents): void
    {
        $path = $this->viewsPath . DIRECTORY_SEPARATOR . str_replace('.', DIRECTORY_SEPARATOR, $name) . '.php';
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents($path, $contents);
    }

    private function deleteRecursive(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        if (is_file($path) || is_link($path)) {
            @unlink($path);

            return;
        }

        $items = scandir($path);
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $this->deleteRecursive($path . DIRECTORY_SEPARATOR . $item);
        }

        @rmdir($path);
    }
}

final class ViewServiceProviderTranslatorStub implements TranslatorInterface
{
    public function __construct(
        private ?string $runtimeLocale = null,
    ) {
    }

    public function setLocale(?string $locale): self
    {
        $this->runtimeLocale = $locale;

        return $this;
    }

    public function locale(): ?string
    {
        return $this->runtimeLocale;
    }

    public function get(string $key, array $replacements = [], ?string $locale = null): string
    {
        $messages = $this->messages($locale);
        $line = $messages[$key] ?? $key;

        foreach ($replacements as $name => $value) {
            $line = str_replace('{' . $name . '}', (string) $value, $line);
        }

        return $line;
    }

    public function group(string $group, ?string $locale = null): array
    {
        if ($group !== 'messages') {
            return [];
        }

        return ['hello' => $this->messages($locale)['messages.hello']];
    }

    public function all(?string $locale = null): array
    {
        return ['messages' => $this->group('messages', $locale)];
    }

    /**
     * @return array<string, string>
     */
    private function messages(?string $locale): array
    {
        $locale ??= $this->runtimeLocale ?? 'en';

        return [
            'messages.hello' => $locale === 'cs' ? 'Ahoj {name}' : 'Hello {name}',
        ];
    }
}

final class ViewServiceProviderLocaleUrlStrategyStub implements LocaleUrlStrategyInterface
{
    public function enabled(): bool
    {
        return true;
    }

    public function localizedRouteName(string $baseRouteName): string
    {
        return 'localized.' . $baseRouteName;
    }

    public function shouldUseLocalizedRoute(string $locale): bool
    {
        return $locale !== 'en';
    }

    public function localeParameter(): string
    {
        return 'locale';
    }
}

final class ViewServiceProviderImageUrlResolverStub implements ImageUrlResolverInterface
{
    public function url(ImageReference $reference): string
    {
        return '/media/' . $reference->publicPath();
    }
}

final class InMemorySession implements SessionInterface
{
    private bool $started = false;
    /** @var array<string, mixed> */
    private array $data = [];

    public function start(): void
    {
        $this->started = true;
    }

    public function started(): bool
    {
        return $this->started;
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
