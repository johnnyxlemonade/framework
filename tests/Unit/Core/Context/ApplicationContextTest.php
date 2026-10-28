<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Core\Context;

use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use PHPUnit\Framework\TestCase;

final class ApplicationContextTest extends TestCase
{
    public function testBasePathReturnsConfiguredBasePath(): void
    {
        $base = $this->basePath();
        $context = $this->context(Environment::Development, DebugMode::enabled(), $base);

        self::assertSame($base, $context->basePath());
    }

    public function testAppPathBuildsPathRelativeToBasePath(): void
    {
        $base = $this->basePath();
        $context = $this->context(Environment::Development, DebugMode::enabled(), $base);

        self::assertSame(
            $base . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Services',
            $context->appPath('Services'),
        );
    }

    public function testConfigPathBuildsPathRelativeToBasePath(): void
    {
        $base = $this->basePath();
        $context = $this->context(Environment::Development, DebugMode::enabled(), $base);

        self::assertSame(
            $base . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'App.php',
            $context->configPath('App.php'),
        );
    }

    public function testStoragePathBuildsPathRelativeToBasePath(): void
    {
        $base = $this->basePath();
        $context = $this->context(Environment::Development, DebugMode::enabled(), $base);

        self::assertSame(
            $base . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs',
            $context->storagePath('logs'),
        );
    }

    public function testPublicPathBuildsPathRelativeToResolvedPublicRoot(): void
    {
        $base = $this->basePath();
        $context = $this->context(Environment::Development, DebugMode::enabled(), $base, $base . DIRECTORY_SEPARATOR . 'public');

        self::assertSame(
            $base . DIRECTORY_SEPARATOR . 'public',
            $context->publicPath(),
        );
    }

    public function testUploadLogSessionAndCachePathsUseExpectedRoots(): void
    {
        $base = $this->basePath();
        $context = $this->context(Environment::Development, DebugMode::enabled(), $base, $base . DIRECTORY_SEPARATOR . 'public');

        self::assertSame(
            $base . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'images',
            $context->uploadPath('images'),
        );
        self::assertSame(
            'uploads/images',
            $context->uploadRelativePath('images'),
        );
        self::assertSame(
            $base . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'writable' . DIRECTORY_SEPARATOR . 'logs',
            $context->resolveLogPath(),
        );
        self::assertSame(
            $base . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'writable' . DIRECTORY_SEPARATOR . 'sessions',
            $context->resolveSessionPath(),
        );
        self::assertSame(
            $base . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache',
            $context->resolveCachePath(),
        );
    }

    public function testEnvironmentReturnsConfiguredEnvironment(): void
    {
        $context = $this->context(Environment::Testing, DebugMode::enabled(), '/tmp/project');

        self::assertSame(Environment::Testing, $context->environment());
    }

    public function testEnvironmentStateHelpersMatchEnvironmentValue(): void
    {
        $path = '/tmp/project';

        $dev = $this->context(Environment::Development, DebugMode::enabled(), $path);
        self::assertTrue($dev->isDevelopment());
        self::assertFalse($dev->isProduction());
        self::assertFalse($dev->isTesting());

        $prod = $this->context(Environment::Production, DebugMode::disabled(), $path);
        self::assertFalse($prod->isDevelopment());
        self::assertTrue($prod->isProduction());
        self::assertFalse($prod->isTesting());

        $test = $this->context(Environment::Testing, DebugMode::enabled(), $path);
        self::assertFalse($test->isDevelopment());
        self::assertFalse($test->isProduction());
        self::assertTrue($test->isTesting());
    }

    public function testDebugReturnsConfiguredDebugModeValue(): void
    {
        $debugContext = $this->context(Environment::Development, DebugMode::enabled(), '/tmp/project');
        $nonDebugContext = $this->context(Environment::Development, DebugMode::disabled(), '/tmp/project');

        self::assertTrue($debugContext->debug());
        self::assertFalse($nonDebugContext->debug());
    }

    public function testResolvedPathsAreIndependentFromCurrentWorkingDirectory(): void
    {
        $base = $this->basePath();
        $context = $this->context(Environment::Development, DebugMode::enabled(), $base, $base . DIRECTORY_SEPARATOR . 'public');
        $cwd = getcwd();

        if (!is_string($cwd)) {
            self::fail('Unable to determine current working directory.');
        }

        $otherDir = sys_get_temp_dir();
        chdir($otherDir);

        try {
            self::assertSame(
                $base . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'files',
                $context->uploadPath('files'),
            );
            self::assertSame(
                $base . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'writable' . DIRECTORY_SEPARATOR . 'logs',
                $context->resolveLogPath(),
            );
        } finally {
            chdir($cwd);
        }
    }

    private function context(Environment $environment, DebugMode $debugMode, string $basePath, ?string $publicPath = null): ApplicationContext
    {
        return new ApplicationContext(
            $environment,
            new Path($basePath, $publicPath),
            $debugMode,
        );
    }

    private function basePath(): string
    {
        return rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'project';
    }
}
