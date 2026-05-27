<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\View;

use Lemonade\Framework\Component\ComponentRegistry;
use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Routing\UrlGenerator;
use Lemonade\Framework\Security\Csrf\CsrfTokenManager;
use Lemonade\Framework\Security\Csrf\CsrfViewHelper;
use Lemonade\Framework\Session\Contract\SessionInterface;
use Lemonade\Framework\Support\BaseUrlResolver;
use Lemonade\Framework\View\View;
use Lemonade\Framework\View\ViewServiceProvider;
use PHPUnit\Framework\TestCase;

final class ViewServiceProviderTest extends TestCase
{
    private string $root;
    private string $viewsPath;

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
        $container = $this->buildContainer(new Config([
            'view' => ['base_path' => $this->viewsPath],
            'app' => ['base_url' => 'https://example.test'],
        ]));
        $provider = new ViewServiceProvider();
        $provider->register($container);

        self::assertTrue($container->isBound(View::class));

        $viewA = $container->get(View::class);
        $viewB = $container->get(View::class);
        self::assertSame($viewA, $viewB);

        $this->writeView('helper', '<?= get_class($component) ?>|<?= get_class($baseUrl) ?>|<?= get_class($url) ?>|<?= get_class($csrf) ?>');
        $output = $viewA->render('helper');

        self::assertStringContainsString(ComponentRegistry::class, $output);
        self::assertStringContainsString(BaseUrlResolver::class, $output);
        self::assertStringContainsString(UrlGenerator::class, $output);
        self::assertStringContainsString(CsrfViewHelper::class, $output);
    }

    public function testRegisterFallsBackToAppViewsWhenBasePathIsNotScalar(): void
    {
        $container = $this->buildContainer(new Config([
            'view' => ['base_path' => ['invalid']],
            'app' => ['base_url' => 'https://example.test'],
        ]));
        $provider = new ViewServiceProvider();
        $provider->register($container);

        $fallbackRoot = $this->root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views';
        $fallbackView = $fallbackRoot . DIRECTORY_SEPARATOR . 'fallback.php';
        if (!is_dir($fallbackRoot)) {
            mkdir($fallbackRoot, 0775, true);
        }
        file_put_contents($fallbackView, 'FALLBACK');

        $cwd = getcwd();
        if (is_string($cwd)) {
            chdir($this->root);
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

    private function buildContainer(Config $config): Container
    {
        $container = new Container();
        $container->singleton(Config::class, $config);
        $container->singleton(ComponentRegistry::class, new ComponentRegistry($container));
        $container->singleton(BaseUrlResolver::class, static fn(): BaseUrlResolver => new BaseUrlResolver($config));
        $container->singleton(UrlGenerator::class, new UrlGenerator(new Router()));
        $session = new InMemorySession();
        $container->singleton(CsrfTokenManager::class, new CsrfTokenManager($session));
        $container->singleton(CsrfViewHelper::class, new CsrfViewHelper($container->get(CsrfTokenManager::class)));

        return $container;
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
