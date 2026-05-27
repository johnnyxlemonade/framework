<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cli;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;

final class ConsoleServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(CommandRegistry::class, static fn(ContainerInterface $container): CommandRegistry => new CommandRegistry($container));
    }
}
