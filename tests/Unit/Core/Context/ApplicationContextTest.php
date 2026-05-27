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

    public function testPublicPathEquivalentBuildsPathRelativeToBasePath(): void
    {
        $base = $this->basePath();
        $context = $this->context(Environment::Development, DebugMode::enabled(), $base);

        self::assertSame(
            $base . DIRECTORY_SEPARATOR . 'public',
            $context->path('public'),
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

    private function context(Environment $environment, DebugMode $debugMode, string $basePath): ApplicationContext
    {
        return new ApplicationContext(
            $environment,
            new Path($basePath),
            $debugMode,
        );
    }

    private function basePath(): string
    {
        return rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'project';
    }
}
