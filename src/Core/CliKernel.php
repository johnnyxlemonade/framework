<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core;

use Lemonade\Framework\Cli\CommandInterface;
use Lemonade\Framework\Cli\CommandRegistry;
use Lemonade\Framework\Cli\ConsoleServiceProvider;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config\ConfigLoader;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Diagnostics\ExceptionLogger;
use Throwable;

final class CliKernel
{
    use KernelBootstrapTrait;

    private bool $booted = false;
    /** @var resource */
    private $stdout;
    /** @var resource */
    private $stderr;

    public function __construct(
        private readonly ApplicationContext $context,
        private readonly ContainerInterface $container,
        private readonly Framework $framework,
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
    }

    /**
     * @param list<string> $argv
     */
    public function handle(array $argv): int
    {
        try {
            $this->benchmark()?->currentOrStart([
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

            return $registry->get($commandName)->run($args);
        } catch (Throwable $exception) {
            $this->logException($exception);

            $this->writeStderr(sprintf("CLI error: %s\n", $exception->getMessage()));

            if ($this->context->debug()) {
                $this->writeStderr($exception->getTraceAsString() . PHP_EOL);
            }

            return 1;
        }
    }

    public function bootstrap(): void
    {
        if ($this->booted) {
            return;
        }

        $this->loadApplicationConfigFiles();
        $this->markBenchmark('config_loaded');

        $this->applyRuntimeAppConfig();
        $this->registerCoreProvidersWithDiagnostics();
        $this->markBenchmark('core_logger_ready');

        $this->framework->register(new ConsoleServiceProvider());
        $this->registerCommonFrameworkProviders();
        $this->markBenchmark('framework_providers_registered');

        $this->registerConfiguredProviders();
        $this->markBenchmark('app_providers_registered');
        $this->markBenchmark('providers_registered');
        $this->registerCliRoutesIfPresent();

        $this->booted = true;
    }

    private function buildCommandRegistry(): CommandRegistry
    {
        $config = $this->container->get(Config::class);
        $configured = $config->get('commands', []);

        if (!is_array($configured)) {
            throw new \LogicException('Config key "commands" must be an array.');
        }

        $registry = $this->container->get(CommandRegistry::class);

        foreach ($configured as $commandClass) {
            if (!is_string($commandClass)) {
                throw new \LogicException('Configured command must be a class-string.');
            }
            if (!class_exists($commandClass) || !is_subclass_of($commandClass, CommandInterface::class)) {
                throw new \LogicException(sprintf(
                    'Configured command "%s" must implement %s.',
                    $commandClass,
                    CommandInterface::class,
                ));
            }
            /** @var class-string<CommandInterface> $commandClass */

            $registry->register($commandClass);
        }

        return $registry;
    }

    private function printCommandList(CommandRegistry $registry): void
    {
        $this->writeStdout("Available commands:\n");

        foreach ($registry->all() as $command) {
            $this->writeStdout(sprintf("  %-24s %s\n", $command->name(), $command->description()));
        }
    }

    private function loadApplicationConfigFiles(): void
    {
        (new ConfigLoader())->loadApplication(
            $this->framework,
            $this->context,
            ConfigLoader::ENTRYPOINT_CLI,
        );
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

    private function registerCliRoutesIfPresent(): void
    {
        $routingConfig = $this->context->configPath('Routing.php');
        if (!is_file($routingConfig)) {
            return;
        }

        $this->framework->routesFromFile($routingConfig);
    }

}
