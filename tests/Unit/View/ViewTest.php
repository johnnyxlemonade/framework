<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\View;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Observability\Benchmark\Benchmark;
use Lemonade\Framework\Support\ServiceLocator;
use Lemonade\Framework\View\View;
use PHPUnit\Framework\TestCase;

final class ViewTest extends TestCase
{
    private string $root;
    private string $viewsPath;

    protected function setUp(): void
    {
        $this->root = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'lemonade-view-' . uniqid('', true);
        $this->viewsPath = $this->root . DIRECTORY_SEPARATOR . 'views';
    }

    protected function tearDown(): void
    {
        $this->deleteRecursive($this->root);
        ServiceLocator::setContainer(new Container());
    }

    public function testRenderSimpleViewAndDataAndSharedData(): void
    {
        $this->writeView('simple', 'Hello <?= $name ?> / <?= $global ?>');
        $view = new View($this->viewsPath);
        $view->share('global', 'shared');

        $output = $view->render('simple', ['name' => 'John']);
        self::assertSame('Hello John / shared', $output);
    }

    public function testSharesAndLocalDataOverrideSharedData(): void
    {
        $this->writeView('override', '<?= $key ?>');
        $view = new View($this->viewsPath);
        $view->shares(['key' => 'shared', 'other' => 'x']);

        $output = $view->render('override', ['key' => 'local']);
        self::assertSame('local', $output);
    }

    public function testPartialRendersAndUsesSharedData(): void
    {
        $this->writeView('part', 'P:<?= $value ?>');
        $view = new View($this->viewsPath);
        $view->share('value', 'shared');

        self::assertSame('P:shared', $view->partial('part'));
    }

    public function testTemplateAndExtendAndContentFlow(): void
    {
        $this->writeView('layouts.main', '[L]<?= $this->content() ?>[/L]');
        $this->writeView('pages.body', 'BODY');
        $this->writeView('pages.child', '<?php $this->extend("layouts.main"); ?>CHILD');
        $view = new View($this->viewsPath);

        self::assertSame('[L]BODY[/L]', $view->template('layouts.main', 'pages.body'));
        self::assertSame('[L]CHILD[/L]', $view->render('pages.child'));
    }

    public function testSectionsStartEndAndDefaultAndEndWithoutStartThrows(): void
    {
        $this->writeView('layouts.section', 'H:<?= $this->section("head", "default-head") ?>|B:<?= $this->content() ?>');
        $this->writeView('pages.sectioned', '<?php $this->extend("layouts.section"); $this->start("head"); ?>TITLE<?php $this->end(); ?>CONTENT');
        $view = new View($this->viewsPath);

        self::assertSame('H:TITLE|B:CONTENT', $view->render('pages.sectioned'));

        $this->writeView('pages.nohead', '<?php $this->extend("layouts.section"); ?>CONTENT');
        self::assertSame('H:default-head|B:CONTENT', $view->render('pages.nohead'));

        $this->expectException(\RuntimeException::class);
        $view->end();
    }

    public function testRenderMissingViewThrowsWithMessageAndDotNotationMapsToPath(): void
    {
        $this->writeView('pages.home', 'HOME');
        $view = new View($this->viewsPath);
        self::assertSame('HOME', $view->render('pages.home'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('View not found');
        $view->render('missing.view');
    }

    public function testRenderResetsSectionsExtendsAndContentBetweenCalls(): void
    {
        $this->writeView('layouts.main', 'LAYOUT:<?= $this->content() ?>:<?= $this->section("head", "none") ?>');
        $this->writeView('pages.withlayout', '<?php $this->extend("layouts.main"); $this->start("head"); ?>H<?php $this->end(); ?>A');
        $this->writeView('plain', 'PLAIN');
        $view = new View($this->viewsPath);

        self::assertSame('LAYOUT:A:H', $view->render('pages.withlayout'));
        self::assertSame('PLAIN', $view->render('plain'));
    }

    public function testRenderExtractsTemplateData(): void
    {
        $this->writeView('extract', '<?= $filename ?>|<?= $name ?>');
        $view = new View($this->viewsPath);

        $output = $view->render('extract', ['filename' => 'injected.php', 'name' => 'John']);
        self::assertSame('injected.php|John', $output);
    }

    public function testRenderMarksBenchmarkWhenAvailableAndDoesNotCrashWithoutContainer(): void
    {
        $this->writeView('bench', 'OK');
        $view = new View($this->viewsPath);

        self::assertSame('OK', $view->render('bench'));

        $container = new Container();
        $benchmark = new Benchmark();
        $run = $benchmark->start();
        $container->singleton(Benchmark::class, $benchmark);
        ServiceLocator::setContainer($container);

        self::assertSame('OK', $view->render('bench'));
        $marks = array_map(static fn(array $mark): string => $mark['name'], $run->marks());
        self::assertContains('view_render_start', $marks);
        self::assertContains('view_render_finished', $marks);
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
