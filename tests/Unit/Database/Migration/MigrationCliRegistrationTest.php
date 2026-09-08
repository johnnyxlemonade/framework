<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Database\Migration;

use Lemonade\Framework\Core\CliKernel;
use Lemonade\Framework\Core\CliKernelFactory;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use PHPUnit\Framework\TestCase;

final class MigrationCliRegistrationTest extends TestCase
{
    private string $root = '';
    /** @var resource|null */
    private $stdout = null;
    /** @var resource|null */
    private $stderr = null;

    protected function setUp(): void
    {
        $this->root = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'lemonade-migration-cli-' . uniqid('', true);
        $this->writeConfig('Config.yaml', "shared:\n  - App\nhttp: []\ncli:\n  - Commands\n");
        $this->writeConfig('App.yaml', "module: app\nconfig: {}\n");
        $this->writeConfig('Commands.yaml', "module: commands\nconfig:\n  commands: []\n");
    }

    protected function tearDown(): void
    {
        foreach ([$this->stdout, $this->stderr] as $stream) {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        $this->deleteDirectory($this->root);
    }

    public function testListIncludesMigrationCommandsWithoutDatabaseConfiguration(): void
    {
        $kernel = $this->kernel();

        self::assertSame(0, $kernel->handle(['bin/lemonade', 'list']));
        self::assertStringContainsString('database:migrate', $this->contents($this->stdout));
        self::assertStringContainsString('database:migrate:status', $this->contents($this->stdout));
    }

    private function kernel(): CliKernel
    {
        $this->stdout ??= $this->stream();
        $this->stderr ??= $this->stream();
        $context = new ApplicationContext(Environment::Testing, new Path($this->root), DebugMode::disabled());

        return (new CliKernelFactory(stdout: $this->stdout, stderr: $this->stderr))->create($context);
    }

    /** @return resource */
    private function stream()
    {
        $stream = fopen('php://temp', 'w+b');
        if (!is_resource($stream)) {
            throw new \RuntimeException('Unable to create temp stream.');
        }

        return $stream;
    }

    /** @param resource|null $stream */
    private function contents($stream): string
    {
        if (!is_resource($stream)) {
            return '';
        }
        rewind($stream);
        $contents = stream_get_contents($stream);

        return is_string($contents) ? $contents : '';
    }

    private function writeConfig(string $file, string $contents): void
    {
        $directory = $this->root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Config';
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        file_put_contents($directory . DIRECTORY_SEPARATOR . $file, $contents);
    }

    private function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
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
            $target = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($target)) {
                $this->deleteDirectory($target);
            } else {
                unlink($target);
            }
        }
        rmdir($path);
    }
}
