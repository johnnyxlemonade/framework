<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Core\Context;

use Lemonade\Framework\Core\Context\ApplicationContextFactory;
use PHPUnit\Framework\TestCase;

final class ApplicationContextFactoryTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'lemonade-context-factory-' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        $this->deleteRecursive($this->root);
    }

    public function testExplicitPublicPathOverrideHasPriority(): void
    {
        $context = (new ApplicationContextFactory())->create(
            $this->root,
            env: ['APP_PUBLIC_PATH' => 'custom-web'],
            server: ['SCRIPT_FILENAME' => $this->root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'index.php'],
        );

        self::assertSame(
            $this->root . DIRECTORY_SEPARATOR . 'custom-web',
            $context->publicPath(),
        );
    }

    public function testHttpEntrypointDirectoryIsUsedAsPublicPathWhenInsideBasePath(): void
    {
        $context = (new ApplicationContextFactory())->create(
            $this->root,
            server: ['SCRIPT_FILENAME' => $this->root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'index.php'],
        );

        self::assertSame(
            $this->root . DIRECTORY_SEPARATOR . 'public',
            $context->publicPath(),
        );
    }

    public function testLegacyHttpEntrypointFallsBackToBasePath(): void
    {
        $context = (new ApplicationContextFactory())->create(
            $this->root,
            server: ['SCRIPT_FILENAME' => $this->root . DIRECTORY_SEPARATOR . 'index.php'],
        );

        self::assertSame($this->root, $context->publicPath());
    }

    public function testExistingPublicDirectoryIsUsedWhenScriptFilenameIsNotAvailable(): void
    {
        $publicDir = $this->root . DIRECTORY_SEPARATOR . 'public';
        mkdir($publicDir, 0775, true);

        $context = (new ApplicationContextFactory())->create($this->root);

        self::assertSame($publicDir, $context->publicPath());
    }

    public function testFallbackWithoutPublicDirectoryUsesBasePath(): void
    {
        $context = (new ApplicationContextFactory())->create($this->root);

        self::assertSame($this->root, $context->publicPath());
    }

    public function testWindowsStylePathsResolveConsistently(): void
    {
        $basePath = 'C:\\laragon\\www\\framework';
        $context = (new ApplicationContextFactory())->create(
            $basePath,
            server: ['SCRIPT_FILENAME' => 'C:\\laragon\\www\\framework\\public\\index.php'],
        );

        self::assertSame('C:\\laragon\\www\\framework', $context->basePath());
        self::assertSame('C:\\laragon\\www\\framework\\public', $context->publicPath());
        self::assertSame('C:\\laragon\\www\\framework\\public\\uploads\\images', $context->uploadPath('images'));
        self::assertSame('C:\\laragon\\www\\framework\\storage\\writable\\logs', $context->resolveLogPath());
        self::assertSame('C:\\laragon\\www\\framework\\storage\\writable\\sessions', $context->resolveSessionPath());
        self::assertSame('C:\\laragon\\www\\framework\\storage\\cache', $context->resolveCachePath());
    }

    public function testUnixStylePathsResolveConsistently(): void
    {
        $basePath = '/var/www/framework';
        $context = (new ApplicationContextFactory())->create(
            $basePath,
            server: ['SCRIPT_FILENAME' => '/var/www/framework/public/index.php'],
        );

        self::assertSame('/var/www/framework', $context->basePath());
        self::assertSame('/var/www/framework/public', $context->publicPath());
        self::assertSame('/var/www/framework/public/uploads/images', $context->uploadPath('images'));
        self::assertSame('/var/www/framework/storage/writable/logs', $context->resolveLogPath());
        self::assertSame('/var/www/framework/storage/writable/sessions', $context->resolveSessionPath());
        self::assertSame('/var/www/framework/storage/cache', $context->resolveCachePath());
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
