<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cli;

use Lemonade\Framework\Container\ScopeFactoryInterface;
use Lemonade\Framework\Container\ScopeKind;

/**
 * Executes a command inside a fresh command scope with invocation-specific services.
 */
final class CommandInvoker
{
    /**
     * Creates an invoker backed by the application's scope factory.
     */
    public function __construct(
        private readonly ScopeFactoryInterface $scopeFactory,
    ) {
    }

    /**
     * Resolves and runs the selected command, always closing its scope afterward.
     *
     * Exceptions from command resolution or execution propagate after the scope is closed.
     *
     * @param list<string> $argv Complete argument vector received by the CLI kernel.
     * @param list<string> $args Arguments after the executable and command name.
     *
     * @throws \RuntimeException When the resolved service does not implement the command contract.
     */
    public function invoke(CommandDefinition $definition, array $argv, array $args, CommandOutput $output): int
    {
        $scope = $this->scopeFactory->beginScope(ScopeKind::Command);
        $scope->bindScopedInstance(CommandContext::class, new CommandContext(
            commandName: $definition->name,
            argv: $argv,
            args: $args,
        ));
        $scope->bindScopedInstance(CommandInput::class, new CommandInput($argv, $args));
        $scope->bindScopedInstance(CommandOutput::class, $output);

        try {
            $command = $scope->get($definition->commandClass);

            if (!$command instanceof CommandInterface) {
                throw new \RuntimeException(sprintf(
                    'CLI command "%s" must resolve to %s.',
                    $definition->commandClass,
                    CommandInterface::class,
                ));
            }

            return $command->run($args);
        } finally {
            $scope->close();
        }
    }
}
