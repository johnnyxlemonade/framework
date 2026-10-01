<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Core\Logging;

use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Core\Logging\Config\LoggingConfig;
use Lemonade\Framework\Core\Logging\LogFilePathResolver;
use Lemonade\Framework\Core\Logging\LogManager;
use Lemonade\Framework\Filesystem\Manager\DirectoryManager;
use PHPUnit\Framework\TestCase;

final class LogManagerTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'lemonade-log-manager-' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->root)) {
            (new DirectoryManager())->delete($this->root);
        }
    }

    public function testAppAndErrorChannelsAlwaysWriteToCanonicalDailyFiles(): void
    {
        $logs = $this->logs(new LoggingConfig(7, false, 0, false));
        $logs->app()->info('application event');
        $logs->error()->error('runtime event');

        self::assertFileExists($this->logFile('app'));
        self::assertFileExists($this->logFile('error'));
        self::assertFalse($logs->enabled('request'));
        self::assertFalse($logs->enabled('benchmark'));
    }

    public function testOptionalChannelsUseCanonicalDailyFilesWhenEnabled(): void
    {
        $logs = $this->logs(new LoggingConfig(7, true, 400, true));
        $logs->request()->warning('request event');
        $logs->benchmark()->info('benchmark event');

        self::assertFileExists($this->logFile('request'));
        self::assertFileExists($this->logFile('benchmark'));
    }

    public function testSharedRetentionAppliesToEveryBuiltInChannel(): void
    {
        $oldFile = dirname($this->logFile('app')) . DIRECTORY_SEPARATOR . 'app-2000-01-01.log';
        @mkdir(dirname($oldFile), 0775, true);
        file_put_contents($oldFile, 'old');
        touch($oldFile, strtotime('-2 days'));

        $this->logs(new LoggingConfig(1, false, 0, false))->app()->info('application event');

        self::assertFileDoesNotExist($oldFile);
    }

    private function logs(LoggingConfig $config): LogManager
    {
        $context = new ApplicationContext(
            Environment::Testing,
            new Path($this->root),
            DebugMode::disabled(),
        );

        return new LogManager(
            config: $config,
            pathResolver: new LogFilePathResolver($context),
            directoryManager: new DirectoryManager(),
        );
    }

    private function logFile(string $channel): string
    {
        return $this->root
            . DIRECTORY_SEPARATOR
            . 'storage'
            . DIRECTORY_SEPARATOR
            . 'writable'
            . DIRECTORY_SEPARATOR
            . 'logs'
            . DIRECTORY_SEPARATOR
            . $channel
            . '-'
            . date('Y-m-d')
            . '.log';
    }
}
