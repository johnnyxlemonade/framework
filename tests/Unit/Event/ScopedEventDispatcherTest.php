<?php
declare(strict_types=1);
namespace Lemonade\Framework\Tests\Unit\Event;
use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\ScopeKind;
use Lemonade\Framework\Event\EventDispatcherInterface;
use Lemonade\Framework\Event\EventListenerDefinition;
use Lemonade\Framework\Event\EventListenerInvoker;
use Lemonade\Framework\Event\EventListenerRegistry;
use Lemonade\Framework\Event\ScopedEventDispatcher;
use PHPUnit\Framework\TestCase;
final class ScopedEventDispatcherTest extends TestCase
{
 public function testDispatchesFromActiveScope(): void { $c=new Container(); $c->scoped(ScopedListener::class, ScopedListener::class); $s=$c->beginScope(ScopeKind::Request); $r=new EventListenerRegistry(); $r->add(new EventListenerDefinition(TestEvent::class, ScopedListener::class)); $r->freeze(); $d=new ScopedEventDispatcher($r,new EventListenerInvoker($s)); $e=new TestEvent(); $d->dispatch($e); self::assertTrue($e->handled); $s->close(); }
 public function testRootCannotResolveScopedDispatcherListener(): void { $c=new Container(); $c->scoped(ScopedListener::class, ScopedListener::class); $this->expectException(\Lemonade\Framework\Container\Exception\ScopedServiceRequestedFromRootException::class); (new ScopedEventDispatcher((function(): EventListenerRegistry {$r=new EventListenerRegistry();$r->add(new EventListenerDefinition(TestEvent::class, ScopedListener::class));$r->freeze();return $r;})(),new EventListenerInvoker($c)))->dispatch(new TestEvent()); }
}
final class TestEvent { public bool $handled=false; }
final class ScopedListener { public function __invoke(TestEvent $event): void {$event->handled=true;} }
