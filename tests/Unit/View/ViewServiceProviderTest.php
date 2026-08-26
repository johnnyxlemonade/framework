<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\View;

use Lemonade\Framework\Component\ComponentRegistry;
use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config\AppConfig;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Core\Framework;
use Lemonade\Framework\Core\Kernel;
use Lemonade\Framework\Http\Psr\ResponseEmitter;
use Lemonade\Framework\Localization\Config\LocalizationConfig;
use Lemonade\Framework\Localization\Config\LocalizationUrlConfig;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Routing\UrlGenerator;
use Lemonade\Framework\Security\Csrf\CsrfTokenManager;
use Lemonade\Framework\Security\Csrf\CsrfViewHelper;
use Lemonade\Framework\Session\Contract\SessionInterface;
use Lemonade\Framework\Support\BaseUrlResolver;
use Lemonade\Framework\View\Config\ViewConfigDefinition;
use Lemonade\Framework\View\Config\ViewConfigResolver;
use Lemonade\Framework\View\View;
use Lemonade\Framework\View\ViewHelpers;
use Lemonade\Framework\View\ViewServiceProvider;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
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

    public function testRegisterBindsViewSingletonWithConfiguredBasePathAndHelpers(): void
    {
        $container = $this->buildContainer($this->viewsPath, 'https://example.test');
        $provider = new ViewServiceProvider();
        $provider->register($container);

        self::assertTrue($container->isBound(View::class));

        $viewA = $container->get(View::class);
        $viewB = $container->get(View::class);
        self::assertSame($viewA, $viewB);

        $this->writeView('helper', '<?= get_class($helpers) ?>|<?= get_class($component) ?>|<?= get_class($baseUrl) ?>|<?= get_class($url) ?>|<?= get_class($csrf) ?>');
        $output = $viewA->render('helper');

        self::assertStringContainsString(ViewHelpers::class, $output);
        self::assertStringContainsString(ComponentRegistry::class, $output);
        self::assertStringContainsString(BaseUrlResolver::class, $output);
        self::assertStringContainsString(UrlGenerator::class, $output);
        self::assertStringContainsString(CsrfViewHelper::class, $output);
    }

    public function testRegisterSharesHelpersVariableIntoRenderedView(): void
    {
        $container = $this->buildContainer($this->viewsPath, 'https://example.test');
        $provider = new ViewServiceProvider();
        $provider->register($container);

        $this->writeView('helpers-variable', '<?= $helpers instanceof ' . '\\' . ViewHelpers::class . ' ? $helpers->asset("css/app.css") : "missing" ?>');

        self::assertSame(
            'https://example.test/css/app.css',
            $container->get(View::class)->render('helpers-variable'),
        );
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
        self::assertTrue($this->hasContainerInstance($container, ViewHelpers::class));
        self::assertTrue($this->hasContainerInstance($container, View::class));
    }

    public function testRepeatedViewResolutionRespectsSingletonLifecycle(): void
    {
        $container = $this->buildContainer($this->viewsPath, 'https://example.test');
        (new ViewServiceProvider())->register($container);

        $viewA = $container->get(View::class);
        $viewB = $container->get(View::class);
        $helpersA = $container->get(ViewHelpers::class);
        $helpersB = $container->get(ViewHelpers::class);

        self::assertSame($viewA, $viewB);
        self::assertSame($helpersA, $helpersB);
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
        self::assertFalse($this->hasContainerInstance($container, View::class));
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
        $session = new InMemorySession();
        $container->singleton(CsrfTokenManager::class, new CsrfTokenManager($session));
        $container->singleton(CsrfViewHelper::class, new CsrfViewHelper($container->get(CsrfTokenManager::class)));
        $container->singleton(TranslatorInterface::class, new ViewServiceProviderTranslatorStub());
        $container->singleton(LocalizationConfig::class, new LocalizationConfig('en', 'en', ['en'], new LocalizationUrlConfig(false, 'localized.', '/{locale}', 'locale', false)));

        return $container;
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

        return new Kernel($context, $container, $framework, new ResponseEmitter());
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
        unset($locale);

        return [$group => $group];
    }

    public function all(?string $locale = null): array
    {
        unset($locale);

        return ['messages' => ['hello' => 'Hello']];
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

    public function regenerate(bool $deleteOldSession = true): void
    {
        unset($deleteOldSession);
    }
}
