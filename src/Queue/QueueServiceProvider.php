<?php

declare(strict_types=1);

namespace Lemonade\Framework\Queue;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Queue\Config\QueueConfig;
use Lemonade\Framework\Queue\Config\QueueConfigDefinition;
use Lemonade\Framework\Queue\Config\QueueConfigResolver;
use Lemonade\Framework\Queue\Transport\DatabaseQueueTransport;
use Lemonade\Framework\Queue\Transport\SyncQueueTransport;

final class QueueServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
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

            $bus = new QueueBus($container, $transports, $default);

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
    }
}
