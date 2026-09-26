<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Cli;

use Lemonade\Framework\Cli\CommandContext;
use Lemonade\Framework\Cli\CommandDefinition;
use Lemonade\Framework\Cli\CommandInput;
use Lemonade\Framework\Cli\CommandInterface;
use Lemonade\Framework\Cli\CommandInvoker;
use Lemonade\Framework\Cli\CommandOutput;
use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\Exception\ScopedContainerClosedException;
use Lemonade\Framework\Container\ScopedContainerInterface;
use PHPUnit\Framework\TestCase;

final class CommandInvokerTest extends TestCase
{
    /** @var resource */
    private $stdout;

    /** @var resource */
    private $stderr;

    protected function setUp(): void
    {
        $stdout = fopen('php://temp', 'w+b');
        $stderr = fopen('php://temp', 'w+b');
        if (!is_resource($stdout) || !is_resource($stderr)) {
            throw new \RuntimeException('Unable to create command output streams.');
        }

        $this->stdout = $stdout;
        $this->stderr = $stderr;

        InvokerScopedCommand::reset();
        InvokerScopeRecordingCommand::$scope = null;
        InvokerFailingScopeCommand::$scope = null;
    }

    protected function tearDown(): void
    {
        fclose($this->stdout);
        fclose($this->stderr);
    }

    public function testInvokesScopedCommandWithContextInputAndOutput(): void
    {
        $container = new Container();
        $container->scoped(InvokerScopedDependency::class, InvokerScopedDependency::class);
        $container->scoped(InvokerScopedCommand::class, InvokerScopedCommand::class);

        $exitCode = $this->invoker($container)->invoke(
            new CommandDefinition('scope:test', InvokerScopedCommand::class, 'Scope test'),
            ['bin/lemonade', 'scope:test', 'first'],
            ['first'],
            $this->commandOutput(),
        );

        self::assertSame(23, $exitCode);
        self::assertSame('scope:test', InvokerScopedCommand::$contexts[0]->commandName);
        self::assertSame('first', InvokerScopedCommand::$inputs[0]->argument(0));
        self::assertSame("command output\n", $this->contents($this->stdout));
        self::assertSame("command error\n", $this->contents($this->stderr));
    }

    public function testSeparateInvocationsDoNotShareScopedCommandOrDependency(): void
    {
        $container = new Container();
        $container->scoped(InvokerScopedDependency::class, InvokerScopedDependency::class);
        $container->scoped(InvokerScopedCommand::class, InvokerScopedCommand::class);
        $invoker = $this->invoker($container);
        $definition = new CommandDefinition('scope:test', InvokerScopedCommand::class, 'Scope test');

        $invoker->invoke($definition, ['bin/lemonade', 'scope:test'], [], $this->commandOutput());
        $invoker->invoke($definition, ['bin/lemonade', 'scope:test'], [], $this->commandOutput());

        self::assertNotSame(InvokerScopedCommand::$commands[0], InvokerScopedCommand::$commands[1]);
        self::assertNotSame(InvokerScopedCommand::$dependencies[0], InvokerScopedCommand::$dependencies[1]);
    }

    public function testScopeClosesAfterSuccess(): void
    {
        $container = new Container();
        $container->scoped(InvokerScopeRecordingCommand::class, InvokerScopeRecordingCommand::class);

        $this->invoker($container)->invoke(
            new CommandDefinition('scope:record', InvokerScopeRecordingCommand::class, 'Scope record'),
            ['bin/lemonade', 'scope:record'],
            [],
            $this->commandOutput(),
        );

        $this->assertScopeClosed(InvokerScopeRecordingCommand::$scope);
    }

    public function testScopeClosesAfterCommandException(): void
    {
        $container = new Container();
        $container->scoped(InvokerFailingScopeCommand::class, InvokerFailingScopeCommand::class);

        try {
            $this->invoker($container)->invoke(
                new CommandDefinition('scope:fail', InvokerFailingScopeCommand::class, 'Scope fail'),
                ['bin/lemonade', 'scope:fail'],
                [],
                $this->commandOutput(),
            );
            self::fail('Expected command exception.');
        } catch (\RuntimeException $exception) {
            self::assertSame('command failed', $exception->getMessage());
        }

        $this->assertScopeClosed(InvokerFailingScopeCommand::$scope);
    }

    private function invoker(Container $container): CommandInvoker
    {
        return new CommandInvoker($container);
    }

    private function commandOutput(): CommandOutput
    {
        return new CommandOutput($this->stdout, $this->stderr);
    }

    /** @param resource $stream */
    private function contents($stream): string
    {
        rewind($stream);
        $contents = stream_get_contents($stream);

        return is_string($contents) ? $contents : '';
    }

    private function assertScopeClosed(?ScopedContainerInterface $scope): void
    {
        self::assertInstanceOf(ScopedContainerInterface::class, $scope);
        $this->expectException(ScopedContainerClosedException::class);
        $scope->get(CommandContext::class);
    }
}

final class InvokerScopedDependency {}

final class InvokerScopedCommand implements CommandInterface
{
    /** @var list<InvokerScopedCommand> */
    public static array $commands = [];

    /** @var list<InvokerScopedDependency> */
    public static array $dependencies = [];

    /** @var list<CommandContext> */
    public static array $contexts = [];

    /** @var list<CommandInput> */
    public static array $inputs = [];

    public function __construct(
        CommandContext $context,
        CommandInput $input,
        private readonly CommandOutput $output,
        InvokerScopedDependency $dependency,
    ) {
        self::$commands[] = $this;
        self::$dependencies[] = $dependency;
        self::$contexts[] = $context;
        self::$inputs[] = $input;
    }

    public static function reset(): void
    {
        self::$commands = [];
        self::$dependencies = [];
        self::$contexts = [];
        self::$inputs = [];
    }

    public function name(): string
    {
        return 'scope:test';
    }

    public function description(): string
    {
        return 'Scope test';
    }

    public function run(array $args): int
    {
        unset($args);
        $this->output->writeln('command output');
        $this->output->errorln('command error');

        return 23;
    }
}

final class InvokerScopeRecordingCommand implements CommandInterface
{
    public static ?ScopedContainerInterface $scope = null;

    public function __construct(ScopedContainerInterface $scope)
    {
        self::$scope = $scope;
    }

    public function name(): string
    {
        return 'scope:record';
    }

    public function description(): string
    {
        return 'Scope record';
    }

    public function run(array $args): int
    {
        return 0;
    }
}

final class InvokerFailingScopeCommand implements CommandInterface
{
    public static ?ScopedContainerInterface $scope = null;

    public function __construct(ScopedContainerInterface $scope)
    {
        self::$scope = $scope;
    }

    public function name(): string
    {
        return 'scope:fail';
    }

    public function description(): string
    {
        return 'Scope fail';
    }

    public function run(array $args): int
    {
        throw new \RuntimeException('command failed');
    }
}
