<?php

declare(strict_types=1);

namespace Lemonade\Framework\Event;

use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Event\Config\EventsConfig;
use Lemonade\Framework\Event\Config\EventsConfigDefinition;
use Lemonade\Framework\Event\Config\EventsConfigResolver;

final class EventServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilderInterface $container): void
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
        $container->singleton(EventListenerRegistry::class, static function (ContainerInterface $container): EventListenerRegistry {
            $registry = new EventListenerRegistry();
            foreach ($container->get(EventsConfig::class)->definitions as $definition) {
                $registry->add($definition);
            }
            $registry->freeze();
            return $registry;
        });
        $container->scoped(EventListenerInvoker::class, EventListenerInvoker::class);
        $container->scoped(EventDispatcherInterface::class, ScopedEventDispatcher::class);
    }
}
