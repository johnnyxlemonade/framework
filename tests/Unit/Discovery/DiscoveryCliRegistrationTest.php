<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Discovery;

use Lemonade\Framework\Core\CliKernel;
use Lemonade\Framework\Core\CliKernelFactory;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use PHPUnit\Framework\TestCase;

final class DiscoveryCliRegistrationTest extends TestCase
{
    private string $root;
    /** @var resource|null */
    private $stdout = null;
    /** @var resource|null */
    private $stderr = null;

    protected function setUp(): void
    {
        $this->root = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'lemonade-discovery-cli-' . uniqid('', true);
        $this->writeConfigFile(
            'Config.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn ['shared' => ['App.php' => null], 'http' => [], 'cli' => ['Commands.php' => null]];\n",
        );
        $this->writeConfigFile('App.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn [];\n");
        $this->writeConfigFile('Commands.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn [];\n");
    }

    protected function tearDown(): void
    {
        if (is_resource($this->stdout)) {
            fclose($this->stdout);
        }
        if (is_resource($this->stderr)) {
            fclose($this->stderr);
        }
        $this->deleteRecursive($this->root);
    }

    public function testDiscoveryCommandIsRegisteredInCliRuntime(): void
    {
        $kernel = $this->kernel();
        $exit = $kernel->handle(['bin/lemonade', 'list']);

        self::assertSame(0, $exit);
        self::assertStringContainsString('discovery:sitemap:generate', $this->stdoutContents());
    }

    private function kernel(): CliKernel
    {
        $this->stdout ??= $this->tempStream();
        $this->stderr ??= $this->tempStream();

        $context = new ApplicationContext(Environment::Testing, new Path($this->root), DebugMode::disabled());

        return (new CliKernelFactory(stdout: $this->stdout, stderr: $this->stderr))->create($context);
    }

    /**
     * @return resource
     */
    private function tempStream()
    {
        $stream = fopen('php://temp', 'w+b');
        if (!is_resource($stream)) {
            throw new \RuntimeException('Unable to create temp stream.');
        }

        return $stream;
    }

    private function stdoutContents(): string
    {
        if (!is_resource($this->stdout)) {
            return '';
        }

        rewind($this->stdout);
        $content = stream_get_contents($this->stdout);

        return is_string($content) ? $content : '';
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

    private function writeConfigFile(string $file, string $contents): void
    {
        $configDir = $this->root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Config';
        if (!is_dir($configDir)) {
            mkdir($configDir, 0775, true);
        }

        file_put_contents($configDir . DIRECTORY_SEPARATOR . $file, $contents);
    }
}
