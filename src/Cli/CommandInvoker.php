<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cli;

use Lemonade\Framework\Container\ScopeFactoryInterface;
use Lemonade\Framework\Container\ScopeKind;

final class CommandInvoker
{
    public function __construct(
        private readonly ScopeFactoryInterface $scopeFactory,
    ) {}

    /**
     * @param list<string> $argv
     * @param list<string> $args
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
