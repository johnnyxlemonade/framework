<?php

declare(strict_types=1);

namespace Lemonade\Framework\Queue;

use Lemonade\Framework\Cli\CommandDefinition;
use Lemonade\Framework\Cli\CommandRegistry;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Container\ScopeFactoryInterface;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Queue\Cli\QueueInstallCommand;
use Lemonade\Framework\Queue\Cli\QueueWorkCommand;
use Lemonade\Framework\Queue\Config\QueueConfig;
use Lemonade\Framework\Queue\Config\QueueConfigDefinition;
use Lemonade\Framework\Queue\Config\QueueConfigResolver;
use Lemonade\Framework\Queue\Transport\DatabaseQueueTransport;
use Lemonade\Framework\Queue\Transport\SyncQueueTransport;

final class QueueServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilderInterface $container): void
    {
        $container->singleton(QueueConfigResolver::class, QueueConfigResolver::class);
        $container->singleton(QueueConfig::class, static function (ContainerInterface $container): QueueConfig {
            return $container
                ->get(QueueConfigResolver::class)
                ->resolve(...$container->get(ConfigDefinitionRegistry::class)->typedEntriesFor(
                    QueueConfigDefinition::moduleKey(),
                    QueueConfigDefinition::class,
                ));
        });
        $container->singleton(MessageSerializer::class, MessageSerializer::class);
        $container->singleton(JobHandlerRegistry::class, JobHandlerRegistry::class);
        $container->singleton(JobHandlerInvoker::class, static function (ContainerInterface $container): JobHandlerInvoker {
            if (!$container instanceof ScopeFactoryInterface) {
                throw new \LogicException(sprintf(
                    'Queue handler invocation requires a container implementing %s.',
                    ScopeFactoryInterface::class,
                ));
            }

            return new JobHandlerInvoker(
                handlers: $container->get(JobHandlerRegistry::class),
                scopeFactory: $container,
            );
        });
        $container->singleton(SyncQueueTransport::class, SyncQueueTransport::class);
        $container->singleton(DatabaseQueueTransport::class, static function (ContainerInterface $container): DatabaseQueueTransport {
            $config = $container->get(QueueConfig::class);

            return new DatabaseQueueTransport(
                db: $container->get(DatabaseDriverInterface::class),
                serializer: $container->get(MessageSerializer::class),
                table: $config->database->table,
                failedTable: $config->database->failedTable,
            );
        });

        $container->singleton(QueueBusInterface::class, static function (ContainerInterface $container): QueueBusInterface {
            $config = $container->get(QueueConfig::class);

            $default = $config->defaultTransport;
            $transportNames = $config->transports;
            $handlers = $config->handlers;
            $transports = [];
            foreach ($transportNames as $name) {
                $transports[$name] = match ($name) {
                    'database' => $container->get(DatabaseQueueTransport::class),
                    default => $container->get(SyncQueueTransport::class),
                };
            }

            if ($transports === []) {
                $transports['sync'] = $container->get(SyncQueueTransport::class);
            }

            $bus = new QueueBus(
                container: $container,
                transports: $transports,
                defaultTransport: $default,
                handlers: $container->get(JobHandlerRegistry::class),
                invoker: $container->get(JobHandlerInvoker::class),
            );

            foreach ($handlers as $messageClass => $handler) {
                if (!is_string($messageClass)) {
                    continue;
                }
                if (is_string($handler) && class_exists($handler)) {
                    /** @var class-string $handler */
                    $bus->addHandler($messageClass, $handler);
                    continue;
                }

                if (is_callable($handler)) {
                    $bus->addHandler(
                        $messageClass,
                        static function (object $message) use ($handler): void {
                            $handler($message);
                        },
                    );
                }
            }

            return $bus;
        });

        $container->singleton('queue', QueueBusInterface::class);
        if ($container->isBound(CommandRegistry::class)) {
            $commands = $container->get(CommandRegistry::class);
            $commands->registerDefinition(new CommandDefinition(
                name: 'queue:install',
                commandClass: QueueInstallCommand::class,
                description: 'Create queue tables for database transport.',
            ));
            $commands->registerDefinition(new CommandDefinition(
                name: 'queue:work',
                commandClass: QueueWorkCommand::class,
                description: 'Process queued jobs from an async transport.',
            ));
        }
    }
}
