<?php

declare(strict_types=1);

namespace Lemonade\Framework\Event;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Event\Config\EventsConfig;
use Lemonade\Framework\Event\Config\EventsConfigDefinition;
use Lemonade\Framework\Event\Config\EventsConfigResolver;

final class EventServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(EventsConfigResolver::class, EventsConfigResolver::class);
        $container->singleton(EventsConfig::class, static function (ContainerInterface $container): EventsConfig {
            return $container
                ->get(EventsConfigResolver::class)
                ->resolve(...$container->get(ConfigDefinitionRegistry::class)->typedEntriesFor(
                    EventsConfigDefinition::moduleKey(),
                    EventsConfigDefinition::class,
                ));
        });
        $container->singleton(EventDispatcherInterface::class, static function (ContainerInterface $container): EventDispatcherInterface {
            $dispatcher = new InMemoryEventDispatcher($container);

            foreach ($container->get(EventsConfig::class)->listeners as $eventClass => $handlers) {
                foreach ($handlers as $handler) {
                    if (is_string($handler) && class_exists($handler)) {
                        /** @var class-string $handler */
                        $dispatcher->addListener($eventClass, $handler);
                        continue;
                    }

                    if (is_callable($handler)) {
                        $dispatcher->addListener(
                            $eventClass,
                            static function (object $event) use ($handler): void {
                                $handler($event);
                            },
                        );
                    }
                }
            }

            return $dispatcher;
        });

        $container->singleton('events', EventDispatcherInterface::class);
    }
}
