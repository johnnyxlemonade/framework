<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Core\Logging;

use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Core\Logging\Exception\LogStorageException;
use Lemonade\Framework\Core\Logging\LogFilePathResolver;
use Lemonade\Framework\Filesystem\Manager\DirectoryManager;
use PHPUnit\Framework\TestCase;

final class LogFilePathResolverTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = rtrim(sys_get_temp_dir(), '/\\')
            . DIRECTORY_SEPARATOR
            . 'lemonade-log-path-'
            . bin2hex(random_bytes(8));

        mkdir($this->root . DIRECTORY_SEPARATOR . 'public', 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->root) || is_link($this->root)) {
            (new DirectoryManager())->delete($this->root);
        }
    }

    public function testBuiltInChannelsResolveToCanonicalStorageFilesWithoutCreatingDirectories(): void
    {
        $resolver = $this->resolver();

        self::assertSame($this->logPath('app.log'), $resolver->resolve('app'));
        self::assertSame($this->logPath('error.log'), $resolver->resolve('error'));
        self::assertSame($this->logPath('request.log'), $resolver->resolve('request'));
        self::assertSame($this->logPath('benchmark.log'), $resolver->resolve('benchmark'));
        self::assertDirectoryDoesNotExist(dirname($this->logPath('app.log')));
    }

    public function testResolverDoesNotChangeStorageOrLogDirectoryPermissions(): void
    {
        mkdir($this->root . '/storage/writable/logs', 0777, true);
        chmod($this->root . '/storage', 0777);
        chmod($this->root . '/storage/writable', 0777);
        chmod($this->root . '/storage/writable/logs', 0777);

        $this->resolver()->resolve('app');

        self::assertSame(0777, fileperms($this->root . '/storage') & 0777);
        self::assertSame(0777, fileperms($this->root . '/storage/writable') & 0777);
        self::assertSame(0777, fileperms($this->root . '/storage/writable/logs') & 0777);
    }

    public function testUnsafeChannelCannotIntroduceTraversal(): void
    {
        $this->expectException(LogStorageException::class);
        $this->expectExceptionMessage('cannot be mapped to a safe filename');

        $this->resolver()->resolve('../error');
    }

    public function testLogStorageInsidePublicWebRootIsRejected(): void
    {
        $context = new ApplicationContext(
            Environment::Testing,
            new Path($this->root, $this->root . '/storage'),
            DebugMode::disabled(),
        );

        $this->expectException(LogStorageException::class);
        $this->expectExceptionMessage('separated from the public web root');

        (new LogFilePathResolver($context))->resolve('app');
    }

    public function testCanonicalPublicWebRootAliasCannotHideStorageOverlap(): void
    {
        mkdir($this->root . '/storage', 0755);
        symlink($this->root . '/storage', $this->root . '/public-storage');
        $context = new ApplicationContext(
            Environment::Testing,
            new Path($this->root, $this->root . '/public-storage'),
            DebugMode::disabled(),
        );

        $this->expectException(LogStorageException::class);
        $this->expectExceptionMessage('separated from the public web root');

        (new LogFilePathResolver($context))->resolve('app');
    }

    public function testSymbolicParentCannotRedirectLogDirectoryOutsideStorage(): void
    {
        mkdir($this->root . '/storage', 0755);
        mkdir($this->root . '/outside', 0755);
        symlink($this->root . '/outside', $this->root . '/storage/writable');

        $this->expectException(LogStorageException::class);
        $this->expectExceptionMessage('symbolic link');

        $this->resolver()->resolve('app');
    }

    public function testSymbolicLinkInLogDirectoryIsRejectedWithoutTouchingItsTarget(): void
    {
        $target = $this->root . '/target';
        mkdir($this->root . '/storage/writable', 0700, true);
        mkdir($target, 0755);
        symlink($target, $this->root . '/storage/writable/logs');

        try {
            $this->resolver()->resolve('app');
            self::fail('A symbolic log directory must be rejected.');
        } catch (LogStorageException $exception) {
            self::assertStringContainsString('symbolic', $exception->getMessage());
        }

        self::assertSame(0755, fileperms($target) & 0777);
    }

    public function testResolverLeavesFilesystemTypeValidationToLogger(): void
    {
        mkdir($this->root . '/storage/writable', 0700, true);
        file_put_contents($this->root . '/storage/writable/logs', 'occupied');

        self::assertSame($this->logPath('app.log'), $this->resolver()->resolve('app'));
    }

    private function resolver(): LogFilePathResolver
    {
        return new LogFilePathResolver(new ApplicationContext(
            Environment::Testing,
            new Path($this->root, $this->root . '/public'),
            DebugMode::disabled(),
        ));
    }

    private function logPath(string $filename): string
    {
        return $this->root
            . DIRECTORY_SEPARATOR
            . 'storage'
            . DIRECTORY_SEPARATOR
            . 'writable'
            . DIRECTORY_SEPARATOR
            . 'logs'
            . DIRECTORY_SEPARATOR
            . $filename;
    }
}
