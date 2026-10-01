<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core;

use Lemonade\Framework\Cli\CommandInvoker;
use Lemonade\Framework\Cli\CommandOutput;
use Lemonade\Framework\Cli\CommandRegistry;
use Lemonade\Framework\Cli\Config\CommandsConfig;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Diagnostics\ExceptionLogger;
use Lemonade\Framework\Core\Diagnostics\PhpDiagnostics;
use Lemonade\Framework\Observability\Benchmark\Benchmark;
use Throwable;

/**
 * CLI application kernel for Lemonade Framework applications.
 *
 * The kernel loads CLI configuration, registers CLI and shared framework
 * service providers, builds the command registry, dispatches commands, and
 * returns the resulting process exit code. It writes output to configurable
 * stdout and stderr streams and converts uncaught exceptions into error output
 * with exit code `1`.
 */
final class CliKernel
{
    private bool $booted = false;
    private readonly ApplicationBootstrapper $bootstrapper;
    private ?CommandRegistry $commandRegistry = null;
    /** @var resource */
    private $stdout;
    /** @var resource */
    private $stderr;

    /**
     * Creates a CLI kernel with its runtime dependencies and output streams.
     *
     * The kernel receives the application context, DI container, and framework
     * runtime used during bootstrap and command dispatch. When stdout or stderr
     * is not provided, the kernel falls back to `STDOUT` and `STDERR`.
     *
     * @throws \InvalidArgumentException If stdout or stderr is not a valid resource.
     */
    public function __construct(
        private readonly ApplicationContext $context,
        private readonly ContainerInterface $container,
        private readonly Framework $framework,
        private readonly Benchmark $benchmark,
        mixed $stdout = null,
        mixed $stderr = null,
    ) {
        if ($stdout !== null && !is_resource($stdout)) {
            throw new \InvalidArgumentException('CliKernel stdout must be a valid resource.');
        }
        if ($stderr !== null && !is_resource($stderr)) {
            throw new \InvalidArgumentException('CliKernel stderr must be a valid resource.');
        }

        $this->stdout = $stdout ?? STDOUT;
        $this->stderr = $stderr ?? STDERR;
        $this->bootstrapper = new ApplicationBootstrapper(
            $this->context,
            $this->container,
            $this->framework,
            $this->benchmark,
        );
    }

    /**
     * Dispatches a CLI command and returns its process exit code.
     *
     * The first argument is treated as the script name, the second as the
     * command name, and the remaining arguments are forwarded to the resolved
     * command. Empty command names, `list`, `--help`, and `-h` print the
     * command list and return `0`. Unknown commands print an error and return
     * `1`. Known commands return their own exit code. Uncaught exceptions are
     * logged, written to stderr, and converted to exit code `1`, with a stack
     * trace included in debug mode.
     *
     * @param list<string> $argv
     */
    public function handle(array $argv): int
    {
        /** @var PhpDiagnostics $phpDiagnostics */
        $phpDiagnostics = $this->container->get(PhpDiagnostics::class);
        $phpDiagnostics->install();

        try {
            $this->benchmark->currentOrStart([
                'entrypoint' => 'cli',
                'started_at' => 'cli-kernel.handle',
            ])->mark('kernel_start');

            $this->bootstrap();

            $registry = $this->buildCommandRegistry();

            $commandName = isset($argv[1]) ? trim($argv[1]) : 'list';
            $args = array_slice($argv, 2);

            if ($commandName === '' || $commandName === 'list' || $commandName === '--help' || $commandName === '-h') {
                $this->printCommandList($registry);

                return 0;
            }

            if (!$registry->has($commandName)) {
                $this->writeStderr(sprintf("Unknown command: %s\n\n", $commandName));
                $this->printCommandList($registry);

                return 1;
            }

            return $this->container
                ->get(CommandInvoker::class)
                ->invoke(
                    definition: $registry->definition($commandName),
                    argv: $argv,
                    args: $args,
                    output: new CommandOutput($this->stdout, $this->stderr),
                );
        } catch (Throwable $exception) {
            $this->logException($exception);

            $this->writeStderr(sprintf("CLI error: %s\n", $exception->getMessage()));

            if ($this->context->debug()) {
                $this->writeStderr($exception->getTraceAsString() . PHP_EOL);
            }

            return 1;
        } finally {
            $phpDiagnostics->uninstall();
        }
    }

    /**
     * Bootstraps the CLI application once for the current kernel instance.
     *
     * The bootstrap is idempotent. It loads CLI configuration files, applies
     * runtime application configuration, registers core diagnostics, registers
     * the console service provider together with common and configured
     * providers, registers the routing file when present, and marks the kernel
     * as booted.
     */
    public function bootstrap(): void
    {
        if ($this->booted) {
            return;
        }

        $this->bootstrapper->bootstrap(BootstrapEntrypoint::Cli);

        $this->booted = true;
    }

    private function buildCommandRegistry(): CommandRegistry
    {
        if ($this->commandRegistry !== null) {
            return $this->commandRegistry;
        }

        $registry = $this->container->get(CommandRegistry::class);

        foreach ($this->container->get(CommandsConfig::class)->definitions as $definition) {
            $registry->registerDefinition($definition);
        }

        foreach ($this->container->get(CommandsConfig::class)->legacyCommandClasses as $commandClass) {
            $registry->register($commandClass);
        }

        return $this->commandRegistry = $registry;
    }

    private function printCommandList(CommandRegistry $registry): void
    {
        $this->writeStdout("Available commands:\n");

        foreach ($registry->allDefinitions() as $definition) {
            $this->writeStdout(sprintf("  %-24s %s\n", $definition->name, $definition->description));
        }
    }

    private function logException(Throwable $exception): void
    {
        $this->container
            ->get(ExceptionLogger::class)
            ->log($exception, 'cli-kernel');
    }

    private function writeStdout(string $message): void
    {
        fwrite($this->stdout, $message);
    }

    private function writeStderr(string $message): void
    {
        fwrite($this->stderr, $message);
    }

}
