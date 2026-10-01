<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Core\Diagnostics;

use ErrorException;
use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Core\Diagnostics\PhpDiagnostics;
use Lemonade\Framework\Core\Diagnostics\ExceptionLogger;
use Lemonade\Framework\Core\Logging\Config\LoggingConfig;
use Lemonade\Framework\Core\Logging\LogFilePathResolver;
use Lemonade\Framework\Core\Logging\LogManager;
use Lemonade\Framework\Filesystem\Manager\DirectoryManager;
use PHPUnit\Framework\TestCase;

final class PhpDiagnosticsTest extends TestCase
{
    private string $root = '';
    private int $originalErrorReporting = 0;
    private string|false $originalDisplayErrors = false;
    private string|false $originalDisplayStartupErrors = false;

    protected function setUp(): void
    {
        $this->root = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'lemonade-php-diagnostics-' . uniqid('', true);
        $this->originalErrorReporting = error_reporting();
        $this->originalDisplayErrors = ini_get('display_errors');
        $this->originalDisplayStartupErrors = ini_get('display_startup_errors');
    }

    protected function tearDown(): void
    {
        error_reporting($this->originalErrorReporting);
        ini_set('display_errors', (string) $this->originalDisplayErrors);
        ini_set('display_startup_errors', (string) $this->originalDisplayStartupErrors);

        if (is_dir($this->root)) {
            (new DirectoryManager())->delete($this->root);
        }
    }

    public function testDevelopmentWarningIsConvertedToErrorException(): void
    {
        $diagnostics = $this->diagnostics(Environment::Development);

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Development warning.');

        $diagnostics->reportError(E_WARNING, 'Development warning.', __FILE__, __LINE__);
    }

    public function testDevelopmentDeprecationIsReportedWithoutThrowing(): void
    {
        $diagnostics = $this->diagnostics(Environment::Development);

        self::assertTrue(
            $diagnostics->reportError(E_DEPRECATED, 'Development deprecation.', __FILE__, __LINE__),
        );
        self::assertStringContainsString('Development deprecation.', $this->errorLogContents());
    }

    public function testProductionWarningDoesNotWriteToOutput(): void
    {
        $diagnostics = $this->diagnostics(Environment::Production);
        $diagnostics->install();

        ob_start();
        trigger_error('Production warning.', E_USER_WARNING);
        $output = ob_get_clean();
        $diagnostics->uninstall();

        self::assertSame('', $output);
        self::assertStringContainsString('Production warning.', $this->errorLogContents());
    }

    public function testInstallControlsDisplayErrorsFromEnvironment(): void
    {
        $production = $this->diagnostics(Environment::Production);
        $production->install();

        self::assertSame('0', ini_get('display_errors'));
        $production->uninstall();

        $development = $this->diagnostics(Environment::Development);
        $development->install();

        self::assertSame('1', ini_get('display_errors'));

        $development->uninstall();
    }

    public function testProductionFatalResponseIsSafe(): void
    {
        $diagnostics = $this->diagnostics(Environment::Production);

        self::assertSame(
            '500 Internal Server Error',
            $diagnostics->fatalResponseBody('Fatal detail.', '/private/path.php', 42),
        );
    }

    public function testDevelopmentFatalResponseContainsDeveloperDetail(): void
    {
        $diagnostics = $this->diagnostics(Environment::Development);

        $body = $diagnostics->fatalResponseBody('Fatal detail.', '/private/path.php', 42);

        self::assertStringContainsString('Fatal detail.', $body);
        self::assertStringContainsString('/private/path.php:42', $body);
    }

    private function diagnostics(Environment $environment): PhpDiagnostics
    {
        $context = new ApplicationContext(
            $environment,
            new Path($this->root),
            DebugMode::disabled(),
        );
        $container = new Container();
        $container->singleton(
            LogManager::class,
            new LogManager(
                new LoggingConfig(
                    retentionDays: 7,
                    requestEnabled: false,
                    requestMinStatus: 0,
                    benchmarkEnabled: false,
                ),
                new LogFilePathResolver($context),
                new DirectoryManager(),
            ),
        );
        $container->singleton(ExceptionLogger::class, new ExceptionLogger($container, $context));

        return new PhpDiagnostics($context, $container);
    }

    private function errorLogContents(): string
    {
        $path = $this->root
            . DIRECTORY_SEPARATOR
            . 'storage'
            . DIRECTORY_SEPARATOR
            . 'writable'
            . DIRECTORY_SEPARATOR
            . 'logs'
            . DIRECTORY_SEPARATOR
            . 'error-' . date('Y-m-d') . '.log';

        $contents = file_get_contents($path);

        return is_string($contents) ? $contents : '';
    }
}
