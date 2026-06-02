<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Core;

use Lemonade\Framework\Cli\CommandInterface;
use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Core\CliKernelFactory;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use PHPUnit\Framework\TestCase;

final class CliKernelFactoryTest extends TestCase
{
    private string $root;

    /** @var resource|null */
    private $stdout = null;

    /** @var resource|null */
    private $stderr = null;

    protected function setUp(): void
    {
        $this->root = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'lemonade-cli-factory-' . uniqid('', true);
        CliKernelFactoryProbeCommand::$lastValue = null;
        $this->writeConfigFile(
            'Config.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn ['shared' => ['App.php' => null], 'http' => [], 'cli' => ['Commands.php' => 'commands']];\n",
        );
        $this->writeConfigFile('App.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn [];\n");
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

    public function testCreateBuildsCliKernelWithProvidedOutputStreams(): void
    {
        $this->writeCommandsConfig([]);
        $kernel = (new CliKernelFactory(
            stdout: $this->stdout(),
            stderr: $this->stderr(),
        ))->create($this->context());

        self::assertSame(0, $kernel->handle(['bin/lemonade', 'list']));
        self::assertStringContainsString('Available commands:', $this->stdoutContents());
        self::assertSame('', $this->stderrContents());
    }

    public function testCreateUsesProvidedContainer(): void
    {
        $this->writeCommandsConfig([CliKernelFactoryProbeCommand::class]);
        $container = new Container();
        $container->singleton(CliKernelFactoryProbeService::class, new CliKernelFactoryProbeService('from-container'));

        $kernel = (new CliKernelFactory(
            container: $container,
            stdout: $this->stdout(),
            stderr: $this->stderr(),
        ))->create($this->context());

        self::assertSame(0, $kernel->handle(['bin/lemonade', 'factory:probe']));
        self::assertSame('from-container', CliKernelFactoryProbeCommand::$lastValue);
    }

    private function context(): ApplicationContext
    {
        return new ApplicationContext(
            Environment::Testing,
            new Path($this->root),
            DebugMode::disabled(),
        );
    }

    /**
     * @return resource
     */
    private function stdout()
    {
        $this->stdout ??= $this->tempStream();

        return $this->stdout;
    }

    /**
     * @return resource
     */
    private function stderr()
    {
        $this->stderr ??= $this->tempStream();

        return $this->stderr;
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
        $contents = stream_get_contents($this->stdout);

        return is_string($contents) ? $contents : '';
    }

    private function stderrContents(): string
    {
        if (!is_resource($this->stderr)) {
            return '';
        }

        rewind($this->stderr);
        $contents = stream_get_contents($this->stderr);

        return is_string($contents) ? $contents : '';
    }

    /**
     * @param list<class-string<CommandInterface>> $commands
     */
    private function writeCommandsConfig(array $commands): void
    {
        $commandsCode = var_export($commands, true);
        $this->writeConfigFile('Commands.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn {$commandsCode};\n");
    }

    private function writeConfigFile(string $file, string $contents): void
    {
        $configDir = $this->root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Config';
        if (!is_dir($configDir)) {
            mkdir($configDir, 0775, true);
        }

        file_put_contents($configDir . DIRECTORY_SEPARATOR . $file, $contents);
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

final class CliKernelFactoryProbeService
{
    public function __construct(
        public readonly string $value,
    ) {}
}

final class CliKernelFactoryProbeCommand implements CommandInterface
{
    public static ?string $lastValue = null;

    public function __construct(
        private readonly CliKernelFactoryProbeService $service,
    ) {}

    public function name(): string
    {
        return 'factory:probe';
    }

    public function description(): string
    {
        return 'Verifies factory container wiring.';
    }

    public function run(array $args): int
    {
        unset($args);
        self::$lastValue = $this->service->value;

        return 0;
    }
}
