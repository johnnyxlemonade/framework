<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Event;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Event\InMemoryEventDispatcher;
use PHPUnit\Framework\TestCase;

final class InMemoryEventDispatcherTest extends TestCase
{
    public function testDispatchReturnsSameEventInstance(): void
    {
        $dispatcher = new InMemoryEventDispatcher(new Container());
        $event = new ChildEvent();

        $returned = $dispatcher->dispatch($event);

        self::assertSame($event, $returned);
    }

    public function testExactClassListenerIsCalledAndCanMutateEvent(): void
    {
        $dispatcher = new InMemoryEventDispatcher(new Container());
        $dispatcher->addListener(ChildEvent::class, static function (object $event): void {
            if (!$event instanceof ChildEvent) {
                return;
            }

            $event->message = 'mutated';
        });

        $event = new ChildEvent();
        $dispatcher->dispatch($event);

        self::assertSame('mutated', $event->message);
    }

    public function testParentAndInterfaceListenersAreCalledForChildEvent(): void
    {
        $dispatcher = new InMemoryEventDispatcher(new Container());
        $recorder = new EventRecorder();

        $dispatcher->addListener(BaseEvent::class, static function (object $event) use ($recorder): void {
            if (!$event instanceof BaseEvent) {
                return;
            }

            unset($event);
            $recorder->events[] = 'parent';
        });
        $dispatcher->addListener(MarkerEventInterface::class, static function (object $event) use ($recorder): void {
            if (!$event instanceof MarkerEventInterface) {
                return;
            }

            unset($event);
            $recorder->events[] = 'interface';
        });

        $dispatcher->dispatch(new ChildEvent());

        self::assertContains('parent', $recorder->events);
        self::assertContains('interface', $recorder->events);
    }

    public function testMultipleListenersRunByPriorityDescending(): void
    {
        $dispatcher = new InMemoryEventDispatcher(new Container());
        $recorder = new EventRecorder();

        $dispatcher->addListener(ChildEvent::class, static function (object $event) use ($recorder): void {
            if (!$event instanceof ChildEvent) {
                return;
            }

            unset($event);
            $recorder->events[] = 'p10';
        }, 10);
        $dispatcher->addListener(ChildEvent::class, static function (object $event) use ($recorder): void {
            if (!$event instanceof ChildEvent) {
                return;
            }

            unset($event);
            $recorder->events[] = 'p100';
        }, 100);
        $dispatcher->addListener(ChildEvent::class, static function (object $event) use ($recorder): void {
            if (!$event instanceof ChildEvent) {
                return;
            }

            unset($event);
            $recorder->events[] = 'p0';
        }, 0);

        $dispatcher->dispatch(new ChildEvent());

        self::assertSame(['p100', 'p10', 'p0'], $recorder->events);
    }

    public function testCallableAndClassStringListenersResolvedFromContainer(): void
    {
        $container = new Container();
        $container->singleton(ClassStringListener::class, ClassStringListener::class);
        $dispatcher = new InMemoryEventDispatcher($container);

        $dispatcher->addListener(ChildEvent::class, static function (object $event): void {
            if (!$event instanceof ChildEvent) {
                return;
            }

            $event->log[] = 'callable';
        }, 20);
        $dispatcher->addListener(ChildEvent::class, ClassStringListener::class, 10);

        $event = new ChildEvent();
        $dispatcher->dispatch($event);

        self::assertSame(['callable', 'class-string'], $event->log);
    }

    public function testInvokableListenerObjectFromContainerIsCalled(): void
    {
        $container = new Container();
        $container->singleton(InvokableListener::class, InvokableListener::class);
        $dispatcher = new InMemoryEventDispatcher($container);
        $dispatcher->addListener(ChildEvent::class, InvokableListener::class);

        $event = new ChildEvent();
        $dispatcher->dispatch($event);

        self::assertSame(['invokable'], $event->log);
    }

    public function testNonCallableResolvedListenerThrowsRuntimeExceptionWithClassName(): void
    {
        $container = new Container();
        $container->singleton(NonCallableListener::class, NonCallableListener::class);
        $dispatcher = new InMemoryEventDispatcher($container);
        $dispatcher->addListener(ChildEvent::class, NonCallableListener::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(NonCallableListener::class);
        $dispatcher->dispatch(new ChildEvent());
    }

    public function testDispatchWithoutListenersReturnsEventAndDoesNotFail(): void
    {
        $dispatcher = new InMemoryEventDispatcher(new Container());
        $event = new ChildEvent();

        $returned = $dispatcher->dispatch($event);

        self::assertSame($event, $returned);
        self::assertSame([], $event->log);
    }

    public function testListenerExceptionBubblesUp(): void
    {
        $dispatcher = new InMemoryEventDispatcher(new Container());
        $dispatcher->addListener(ChildEvent::class, static function (object $event): void {
            if (!$event instanceof ChildEvent) {
                return;
            }

            unset($event);
            throw new \RuntimeException('listener-failed');
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('listener-failed');
        $dispatcher->dispatch(new ChildEvent());
    }
}

interface MarkerEventInterface {}

class BaseEvent {}

final class ChildEvent extends BaseEvent implements MarkerEventInterface
{
    public string $message = 'initial';
    /** @var list<string> */
    public array $log = [];
}

final class EventRecorder
{
    /** @var list<string> */
    public array $events = [];
}

final class ClassStringListener
{
    public function __invoke(ChildEvent $event): void
    {
        $event->log[] = 'class-string';
    }
}

final class InvokableListener
{
    public function __invoke(ChildEvent $event): void
    {
        $event->log[] = 'invokable';
    }
}

final class NonCallableListener
{
    public string $value = 'x';
}
