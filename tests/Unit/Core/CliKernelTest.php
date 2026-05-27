<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Core;

use Lemonade\Framework\Cli\CommandInterface;
use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Core\CliKernel;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Core\Framework;
use PHPUnit\Framework\TestCase;

final class CliKernelTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'lemonade-cli-' . uniqid('', true);
        CliKernelRecorderCommand::reset();
        CliKernelFailingCommand::reset();
    }

    protected function tearDown(): void
    {
        $this->deleteRecursive($this->root);
    }

    public function testHandleWithoutCommandPrintsListAndReturnsZero(): void
    {
        $this->writeCommandsConfig([CliKernelRecorderCommand::class]);
        $kernel = $this->kernel();

        $exit = $kernel->handle(['bin/lemonade']);

        self::assertSame(0, $exit);
    }

    public function testHandleListPrintsListAndReturnsZero(): void
    {
        $this->writeCommandsConfig([CliKernelRecorderCommand::class]);
        $kernel = $this->kernel();

        $exit = $kernel->handle(['bin/lemonade', 'list']);

        self::assertSame(0, $exit);
    }

    public function testHandleHelpPrintsListAndReturnsZero(): void
    {
        $this->writeCommandsConfig([CliKernelRecorderCommand::class]);
        $kernel = $this->kernel();

        self::assertSame(0, $kernel->handle(['bin/lemonade', '--help']));
        self::assertSame(0, $kernel->handle(['bin/lemonade', '-h']));
    }

    public function testUnknownCommandReturnsOne(): void
    {
        $this->writeCommandsConfig([CliKernelRecorderCommand::class]);
        $kernel = $this->kernel();

        $exit = $kernel->handle(['bin/lemonade', 'unknown']);

        self::assertSame(1, $exit);
    }

    public function testKnownCommandRunsWithArgsAndReturnsItsExitCode(): void
    {
        $this->writeCommandsConfig([CliKernelRecorderCommand::class]);
        $kernel = $this->kernel();

        $exit = $kernel->handle(['bin/lemonade', 'recorder', 'first', 'second']);

        self::assertSame(12, $exit);
        self::assertSame(['first', 'second'], CliKernelRecorderCommand::$lastArgs);
        self::assertSame(1, CliKernelRecorderCommand::$runCount);
    }

    public function testCommandsConfigMustBeArray(): void
    {
        $this->writeConfigFile('Commands.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn ['commands' => 'invalid'];\n");
        $kernel = $this->kernel();

        self::assertSame(1, $kernel->handle(['bin/lemonade', 'list']));
    }

    public function testConfiguredCommandMustBeString(): void
    {
        $this->writeConfigFile('Commands.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn ['commands' => [123]];\n");
        $kernel = $this->kernel();

        self::assertSame(1, $kernel->handle(['bin/lemonade', 'list']));
    }

    public function testConfiguredCommandMustExistAndImplementInterface(): void
    {
        $this->writeConfigFile('Commands.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn ['commands' => ['Missing\\\\Command']];\n");
        $kernelMissing = $this->kernel();
        self::assertSame(1, $kernelMissing->handle(['bin/lemonade', 'list']));

        $this->writeConfigFile('Commands.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn ['commands' => ['" . CliKernelNotACommand::class . "']];\n");
        $kernelInvalid = $this->kernel();
        self::assertSame(1, $kernelInvalid->handle(['bin/lemonade', 'list']));
    }

    public function testManifestFilesAreUsedWhenManifestExists(): void
    {
        $this->writeConfigFile('Config.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn ['files' => ['AltCommands.php']];\n");
        $this->writeConfigFile('AltCommands.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn ['commands' => ['" . CliKernelRecorderCommand::class . "']];\n");

        $kernel = $this->kernel();
        $exit = $kernel->handle(['bin/lemonade', 'recorder']);

        self::assertSame(12, $exit);
    }

    public function testManifestAutoAddsCommandsPhpWhenMissingInFilesList(): void
    {
        $this->writeConfigFile('Config.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn ['files' => ['App.php']];\n");
        $this->writeConfigFile('App.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn ['app' => ['name' => 'Demo']];\n");
        $this->writeConfigFile('Commands.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn ['commands' => ['" . CliKernelRecorderCommand::class . "']];\n");

        $kernel = $this->kernel();
        $exit = $kernel->handle(['bin/lemonade', 'recorder']);

        self::assertSame(12, $exit);
    }

    public function testInvalidManifestNotReturningArrayReturnsOne(): void
    {
        $this->writeConfigFile('Config.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn 'invalid';\n");
        $kernel = $this->kernel();

        self::assertSame(1, $kernel->handle(['bin/lemonade', 'list']));
    }

    public function testInvalidManifestWithoutFilesKeyReturnsOne(): void
    {
        $this->writeConfigFile('Config.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn ['invalid' => []];\n");
        $kernel = $this->kernel();

        self::assertSame(1, $kernel->handle(['bin/lemonade', 'list']));
    }

    public function testInvalidManifestFileNameReturnsOne(): void
    {
        $this->writeConfigFile('Config.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn ['files' => ['']];\n");
        $kernel = $this->kernel();

        self::assertSame(1, $kernel->handle(['bin/lemonade', 'list']));
    }

    public function testExceptionFromCommandIsLoggedAndReturnsOne(): void
    {
        $this->writeCommandsConfig([CliKernelFailingCommand::class]);
        $kernel = $this->kernel();

        $exit = $kernel->handle(['bin/lemonade', 'failing']);

        self::assertSame(1, $exit);

        $logPath = $this->root
            . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'writable'
            . DIRECTORY_SEPARATOR . 'logs'
            . DIRECTORY_SEPARATOR . 'error-' . date('Y-m-d') . '.log';
        self::assertFileExists($logPath);
        $contents = file_get_contents($logPath);
        self::assertIsString($contents);
        self::assertStringContainsString('Command failed hard', $contents);
    }

    private function kernel(): CliKernel
    {
        $context = new ApplicationContext(
            Environment::Testing,
            new Path($this->root),
            DebugMode::disabled(),
        );
        $container = new Container();
        $framework = new Framework($container, $context);

        return new CliKernel($context, $container, $framework);
    }

    /**
     * @param list<class-string<CommandInterface>> $commands
     */
    private function writeCommandsConfig(array $commands): void
    {
        $commandsCode = var_export($commands, true);
        $this->writeConfigFile('Commands.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn ['commands' => {$commandsCode}];\n");
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

final class CliKernelRecorderCommand implements CommandInterface
{
    /** @var list<string>|null */
    public static ?array $lastArgs = null;
    public static int $runCount = 0;

    public static function reset(): void
    {
        self::$lastArgs = null;
        self::$runCount = 0;
    }

    public function name(): string
    {
        return 'recorder';
    }

    public function description(): string
    {
        return 'Records args.';
    }

    public function run(array $args): int
    {
        self::$runCount++;
        self::$lastArgs = array_values($args);

        return 12;
    }
}

final class CliKernelFailingCommand implements CommandInterface
{
    public static int $runCount = 0;

    public static function reset(): void
    {
        self::$runCount = 0;
    }

    public function name(): string
    {
        return 'failing';
    }

    public function description(): string
    {
        return 'Always fails.';
    }

    public function run(array $args): int
    {
        self::$runCount++;
        throw new \RuntimeException('Command failed hard');
    }
}

final class CliKernelNotACommand {}
