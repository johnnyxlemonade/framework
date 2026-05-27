<?php

declare(strict_types=1);

namespace Lemonade\Framework\Queue;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Queue\Transport\DatabaseQueueTransport;
use Lemonade\Framework\Queue\Transport\SyncQueueTransport;

final class QueueServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(MessageSerializer::class, MessageSerializer::class);
        $container->singleton(SyncQueueTransport::class, SyncQueueTransport::class);
        $container->singleton(DatabaseQueueTransport::class, static function (ContainerInterface $container): DatabaseQueueTransport {
            /** @var Config $config */
            $config = $container->get(Config::class);

            return new DatabaseQueueTransport(
                db: $container->get(DatabaseDriverInterface::class),
                serializer: $container->get(MessageSerializer::class),
                table: $config->string('queue.database.table', 'system_queue_job') ?? 'system_queue_job',
                failedTable: $config->string('queue.database.failed_table', 'system_queue_failed_job') ?? 'system_queue_failed_job',
            );
        });

        $container->singleton(QueueBusInterface::class, static function (ContainerInterface $container): QueueBusInterface {
            /** @var Config $config */
            $config = $container->get(Config::class);

            $default = $config->string('queue.default', 'sync') ?? 'sync';
            $transportNames = $config->get('queue.transports', ['sync']);
            $handlers = $config->get('queue.handlers', []);

            $transports = [];
            if (is_array($transportNames)) {
                foreach ($transportNames as $name) {
                    if (!is_string($name)) {
                        continue;
                    }

                    $transports[$name] = match ($name) {
                        'database' => $container->get(DatabaseQueueTransport::class),
                        default => $container->get(SyncQueueTransport::class),
                    };
                }
            }

            if ($transports === []) {
                $transports['sync'] = $container->get(SyncQueueTransport::class);
            }

            $bus = new QueueBus($container, $transports, $default);

            if (is_array($handlers)) {
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
            }

            return $bus;
        });

        $container->singleton('queue', QueueBusInterface::class);
    }
}
