<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cli;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Container\ScopeFactoryInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;

final class ConsoleServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(CommandRegistry::class, static fn(ContainerInterface $container): CommandRegistry => new CommandRegistry($container));
        $container->singleton(CommandInvoker::class, static function (ContainerInterface $container): CommandInvoker {
            if (!$container instanceof ScopeFactoryInterface) {
                throw new \LogicException(sprintf(
                    'CLI command invocation requires a container implementing %s.',
                    ScopeFactoryInterface::class,
                ));
            }

            return new CommandInvoker($container);
        });
    }
}
