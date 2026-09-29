<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Event;

use Lemonade\Framework\Event\Config\EventsConfigDefinition;
use Lemonade\Framework\Event\Config\EventsConfigResolver;
use PHPUnit\Framework\TestCase;

final class EventsConfigResolverTest extends TestCase
{
    public function testMergesListenersForTheSameEventAcrossDefinitionsInRegistrationOrder(): void
    {
        $config = (new EventsConfigResolver())->resolve(
            EventsConfigDefinition::create()->listener(UserRegistered::class, FirstListener::class),
            EventsConfigDefinition::create()->listener(UserRegistered::class, SecondListener::class),
        );

        self::assertSame([FirstListener::class, SecondListener::class], array_map(static fn($definition): string => $definition->listenerClass, $config->definitions));
    }

    public function testKeepsMultipleListenersAndSeparateEventClasses(): void
    {
        $config = (new EventsConfigResolver())->resolve(
            EventsConfigDefinition::create()
                ->listener(UserRegistered::class, FirstListener::class)
                ->listener(UserRegistered::class, SecondListener::class)
                ->listener(UserDeleted::class, FirstListener::class),
        );

        self::assertCount(3, $config->definitions);
        self::assertSame(UserDeleted::class, $config->definitions[2]->eventClass);
    }
}

final class UserRegistered {}
final class UserDeleted {}
final class FirstListener {}
final class SecondListener {}
