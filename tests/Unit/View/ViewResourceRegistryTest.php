<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\View;

use InvalidArgumentException;
use Lemonade\Framework\View\View;
use Lemonade\Framework\View\ViewResourceRegistry;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ViewResourceRegistryTest extends TestCase
{
    private string $root = '';
    private string $legacyRoot = '';
    private string $usersRoot = '';
    private string $adminRoot = '';

    protected function setUp(): void
    {
        $this->root = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'lemonade-view-resources-' . uniqid('', true);
        $this->legacyRoot = $this->root . DIRECTORY_SEPARATOR . 'legacy';
        $this->usersRoot = $this->root . DIRECTORY_SEPARATOR . 'users';
        $this->adminRoot = $this->root . DIRECTORY_SEPARATOR . 'admin';

        mkdir($this->legacyRoot, 0775, true);
        mkdir($this->usersRoot, 0775, true);
        mkdir($this->adminRoot, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->deleteRecursive($this->root);
    }

    public function testLegacyViewsRemainCompatibleAlongsideProviderViews(): void
    {
        $this->writeView($this->legacyRoot, 'frontend.home', 'LEGACY');
        $this->writeView($this->legacyRoot, 'errors/404', 'NOT FOUND');
        $this->writeView($this->usersRoot, 'editor', 'USERS');
        $registry = new ViewResourceRegistry();
        $registry->register('users', $this->usersRoot);
        $view = new View($this->legacyRoot, $registry);

        self::assertSame('LEGACY', $view->render('frontend.home'));
        self::assertSame('NOT FOUND', $view->render('errors/404'));
        self::assertSame('USERS', $view->render('users::editor'));
    }

    public function testResolvesViewsFromTwoProviderNamespaces(): void
    {
        $this->writeView($this->usersRoot, 'editor', 'USERS');
        $this->writeView($this->adminRoot, 'dashboard.index', 'ADMIN');
        $registry = new ViewResourceRegistry();
        $registry->register('users', $this->usersRoot);
        $registry->register('admin', $this->adminRoot);
        $view = new View($this->legacyRoot, $registry);

        self::assertSame('USERS', $view->render('users::editor'));
        self::assertSame('ADMIN', $view->render('admin::dashboard.index'));
    }

    public function testRejectsDuplicateNamespace(): void
    {
        $registry = new ViewResourceRegistry();
        $registry->register('users', $this->usersRoot);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('already registered');
        $registry->register('users', $this->adminRoot);
    }

    public function testRejectsInvalidNamespaceAndMissingRoot(): void
    {
        $registry = new ViewResourceRegistry();

        try {
            $registry->register('Users', $this->usersRoot);
            self::fail('Expected invalid namespace to throw.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('Invalid view namespace', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('View resource root does not exist');
        $registry->register('users', $this->root . DIRECTORY_SEPARATOR . 'missing');
    }

    public function testRejectsMissingNamespaceAndView(): void
    {
        $registry = new ViewResourceRegistry();
        $registry->register('users', $this->usersRoot);
        $view = new View($this->legacyRoot, $registry);

        try {
            $view->render('admin::dashboard');
            self::fail('Expected missing namespace to throw.');
        } catch (RuntimeException $exception) {
            self::assertSame('View namespace is not registered: admin', $exception->getMessage());
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('View not found: users::missing');
        $view->render('users::missing');
    }

    public function testRejectsNonCanonicalNamespacedViewNames(): void
    {
        $registry = new ViewResourceRegistry();
        $registry->register('users', $this->usersRoot);

        $rejected = 0;
        foreach (['../secret', 'partials/row', 'partials\\row', '/tmp/secret', "safe\0name", 'editor::other'] as $name) {
            try {
                $registry->resolve('users', $name);
                self::fail(sprintf('Expected invalid view name to throw: %s', $name));
            } catch (InvalidArgumentException) {
                $rejected++;
            }
        }

        self::assertSame(6, $rejected);
    }

    public function testRejectsSymlinkThatEscapesRegisteredRoot(): void
    {
        $outside = $this->root . DIRECTORY_SEPARATOR . 'outside.php';
        file_put_contents($outside, 'OUTSIDE');

        if (!symlink($outside, $this->usersRoot . DIRECTORY_SEPARATOR . 'escape.php')) {
            self::markTestSkipped('The environment does not permit symbolic links.');
        }

        $registry = new ViewResourceRegistry();
        $registry->register('users', $this->usersRoot);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('escapes its registered root');
        $registry->resolve('users', 'escape');
    }

    public function testProviderViewCanUseAnotherProviderLayoutThroughExtend(): void
    {
        $this->writeView($this->adminRoot, 'layouts.admin', '[<?= $this->content() ?>]');
        $this->writeView($this->usersRoot, 'editor', '<?php $this->extend("admin::layouts.admin"); ?>EDITOR');
        $view = $this->viewWithResources();

        self::assertSame('[EDITOR]', $view->render('users::editor'));
    }

    public function testTemplateResolvesProviderLayoutAndContent(): void
    {
        $this->writeView($this->adminRoot, 'layouts.admin', '[<?= $this->content() ?>]');
        $this->writeView($this->usersRoot, 'editor', 'EDITOR');
        $view = $this->viewWithResources();

        self::assertSame('[EDITOR]', $view->template('admin::layouts.admin', 'users::editor'));
    }

    public function testProviderViewCanRenderItsOwnPartial(): void
    {
        $this->writeView($this->usersRoot, 'partials.row', 'ROW');
        $this->writeView($this->usersRoot, 'index', 'INDEX:<?= $this->partial("users::partials.row") ?>');
        $view = $this->viewWithResources();

        self::assertSame('INDEX:ROW', $view->render('users::index'));
    }

    public function testFirstViewResolutionFreezesRegistry(): void
    {
        $this->writeView($this->legacyRoot, 'home', 'HOME');
        $registry = new ViewResourceRegistry();
        $view = new View($this->legacyRoot, $registry);

        self::assertSame('HOME', $view->render('home'));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('before view resolution begins');
        $registry->register('users', $this->usersRoot);
    }

    public function testDirectRegistryResolutionAlsoFreezesRegistration(): void
    {
        $this->writeView($this->usersRoot, 'index', 'USERS');
        $registry = new ViewResourceRegistry();
        $registry->register('users', $this->usersRoot);

        self::assertStringEndsWith('index.php', $registry->resolve('users', 'index'));

        $this->expectException(LogicException::class);
        $registry->register('admin', $this->adminRoot);
    }

    private function viewWithResources(): View
    {
        $registry = new ViewResourceRegistry();
        $registry->register('users', $this->usersRoot);
        $registry->register('admin', $this->adminRoot);

        return new View($this->legacyRoot, $registry);
    }

    private function writeView(string $root, string $name, string $contents): void
    {
        $path = $root . DIRECTORY_SEPARATOR . str_replace('.', DIRECTORY_SEPARATOR, $name) . '.php';
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($path, $contents);
    }

    private function deleteRecursive(string $path): void
    {
        if (!file_exists($path) && !is_link($path)) {
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
