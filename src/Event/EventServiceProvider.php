<?php

declare(strict_types=1);

namespace Lemonade\Framework\Event;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Core\ServiceProviderInterface;

final class EventServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(EventDispatcherInterface::class, static function (ContainerInterface $container): EventDispatcherInterface {
            $dispatcher = new InMemoryEventDispatcher($container);

            /** @var Config $config */
            $config = $container->get(Config::class);
            $listeners = $config->get('events.listeners', []);

            if (is_array($listeners)) {
                foreach ($listeners as $eventClass => $handlers) {
                    if (!is_string($eventClass)) {
                        continue;
                    }

                    $handlerList = is_array($handlers) ? $handlers : [$handlers];
                    foreach ($handlerList as $handler) {
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
            }

            return $dispatcher;
        });

        $container->singleton('events', EventDispatcherInterface::class);
    }
}
