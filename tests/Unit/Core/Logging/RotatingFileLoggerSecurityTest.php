<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Core\Logging;

use Lemonade\Framework\Core\Logging\Exception\LogStorageException;
use Lemonade\Framework\Core\Logging\RotatingFileLogger;
use Lemonade\Framework\Filesystem\Manager\DirectoryManager;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

final class RotatingFileLoggerSecurityTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = rtrim(sys_get_temp_dir(), '/\\')
            . DIRECTORY_SEPARATOR
            . 'lemonade-logger-security-'
            . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->root) || is_link($this->root)) {
            (new DirectoryManager())->delete($this->root);
        }
    }

    public function testCreatesPrivateDirectoryAndFileDespitePermissiveUmask(): void
    {
        $previousUmask = umask(0000);

        try {
            $this->logger()->info('private record');
        } finally {
            umask($previousUmask);
        }

        self::assertSame(0700, fileperms($this->root . '/logs') & 0777);
        self::assertSame(0600, fileperms($this->dailyFile()) & 0777);
    }

    public function testExistingLogFileIsRestrictedBeforeAppending(): void
    {
        mkdir($this->root . '/logs', 0700, true);
        file_put_contents($this->dailyFile(), "old\n");
        chmod($this->dailyFile(), 0644);

        $this->logger()->info('new');

        self::assertSame(0600, fileperms($this->dailyFile()) & 0777);
        self::assertStringStartsWith("old\n", (string) file_get_contents($this->dailyFile()));
    }

    public function testRotationRestrictsRetainedFilesAndDeletesExpiredFiles(): void
    {
        mkdir($this->root . '/logs', 0700, true);
        $expired = $this->root . '/logs/app-2000-01-01.log';
        $retained = $this->root . '/logs/app-' . date('Y-m-d', strtotime('-1 day')) . '.log';
        file_put_contents($expired, 'expired');
        file_put_contents($retained, 'retained');
        chmod($expired, 0644);
        chmod($retained, 0644);
        touch($expired, strtotime('-10 days'));
        touch($retained, strtotime('-1 hour'));

        $this->logger(2)->info('rotate');

        self::assertFileDoesNotExist($expired);
        self::assertSame(0600, fileperms($retained) & 0777);
    }

    public function testSymbolicCurrentLogIsRejectedWithoutWritingToTarget(): void
    {
        mkdir($this->root . '/logs', 0700, true);
        $target = $this->root . '/target.txt';
        file_put_contents($target, 'secret');
        chmod($target, 0644);
        symlink($target, $this->dailyFile());

        try {
            $this->logger()->info('must not escape');
            self::fail('A symbolic log file must be rejected.');
        } catch (LogStorageException $exception) {
            self::assertStringContainsString('non-symbolic regular file', $exception->getMessage());
        }

        self::assertSame('secret', file_get_contents($target));
        self::assertSame(0644, fileperms($target) & 0777);
    }

    public function testSymbolicRotationCandidateIsRejectedWithoutDeletingTarget(): void
    {
        mkdir($this->root . '/logs', 0700, true);
        $target = $this->root . '/target.txt';
        $candidate = $this->root . '/logs/app-2000-01-01.log';
        file_put_contents($target, 'keep');
        symlink($target, $candidate);

        try {
            $this->logger()->info('must not rotate through link');
            self::fail('A symbolic rotation candidate must be rejected.');
        } catch (LogStorageException $exception) {
            self::assertStringContainsString('non-symbolic regular file', $exception->getMessage());
        }

        self::assertFileExists($candidate);
        self::assertSame('keep', file_get_contents($target));
    }

    public function testTraversalInDirectConstructionIsRejected(): void
    {
        mkdir($this->root . '/logs', 0700, true);
        $logger = new RotatingFileLogger(
            $this->root . '/logs/../escaped.log',
            new DirectoryManager(),
        );

        $this->expectException(LogStorageException::class);
        $this->expectExceptionMessage('without traversal');

        $logger->info('must not escape');
    }

    public function testUnsafeExistingDirectoryIsRejectedInsteadOfSilentlyRepermissioned(): void
    {
        mkdir($this->root . '/logs', 0777, true);
        chmod($this->root . '/logs', 0777);

        $this->expectException(LogStorageException::class);
        $this->expectExceptionMessage('mode 0700');

        $this->logger()->info('must fail');
    }

    #[RequiresPhpExtension('pcntl')]
    public function testConcurrentWritersProduceCompletePrivateRecords(): void
    {
        mkdir($this->root . '/logs', 0700, true);
        $children = [];

        for ($worker = 0; $worker < 4; ++$worker) {
            $processId = pcntl_fork();

            self::assertNotSame(-1, $processId);

            if ($processId === 0) {
                for ($record = 0; $record < 25; ++$record) {
                    $this->logger()->info('concurrent', [
                        'worker' => $worker,
                        'record' => $record,
                    ]);
                }

                exit(0);
            }

            $children[] = $processId;
        }

        foreach ($children as $processId) {
            pcntl_waitpid($processId, $status);
            self::assertTrue(pcntl_wifexited($status));
            self::assertSame(0, pcntl_wexitstatus($status));
        }

        $lines = file($this->dailyFile(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        self::assertIsArray($lines);
        self::assertCount(100, $lines);
        self::assertSame(0600, fileperms($this->dailyFile()) & 0777);

        foreach ($lines as $line) {
            self::assertIsArray(json_decode($line, true, 16, JSON_THROW_ON_ERROR));
        }
    }

    #[RequiresPhpExtension('pcntl')]
    public function testRotationSkipsLockedFileAndRemovesItAfterRelease(): void
    {
        mkdir($this->root . '/logs', 0700, true);
        $expired = $this->root . '/logs/app-2000-01-01.log';
        file_put_contents($expired, 'active');
        chmod($expired, 0600);
        touch($expired, strtotime('-10 days'));
        $handle = fopen($expired, 'r+b');

        self::assertIsResource($handle);
        self::assertTrue(flock($handle, LOCK_EX));

        $processId = pcntl_fork();
        self::assertNotSame(-1, $processId);

        if ($processId === 0) {
            $this->logger()->info('rotation while active');
            exit(0);
        }

        pcntl_waitpid($processId, $status);
        self::assertSame(0, pcntl_wexitstatus($status));
        self::assertFileExists($expired);

        flock($handle, LOCK_UN);
        fclose($handle);

        $this->logger()->info('rotation after release');

        self::assertFileDoesNotExist($expired);
    }

    private function logger(int $retentionDays = 7): RotatingFileLogger
    {
        return new RotatingFileLogger(
            $this->root . '/logs/app.log',
            new DirectoryManager(),
            $retentionDays,
        );
    }

    private function dailyFile(): string
    {
        return $this->root . '/logs/app-' . date('Y-m-d') . '.log';
    }
}
