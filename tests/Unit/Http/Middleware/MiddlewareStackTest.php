<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Http\Middleware;

use Lemonade\Framework\Http\Middleware\MiddlewareStack;
use PHPUnit\Framework\TestCase;

final class MiddlewareStackTest extends TestCase
{
    public function testKeepsGivenOrderDeterministic(): void
    {
        $stack = new MiddlewareStack([
            DummyMiddlewareA::class,
            DummyMiddlewareB::class,
            DummyMiddlewareC::class,
        ]);

        self::assertSame([
            DummyMiddlewareA::class,
            DummyMiddlewareB::class,
            DummyMiddlewareC::class,
        ], $stack->all());
    }

    public function testCanAddPrependInsertBeforeInsertAfterAndRemove(): void
    {
        $stack = new MiddlewareStack([DummyMiddlewareA::class, DummyMiddlewareC::class]);

        $stack->add(DummyMiddlewareD::class);
        $stack->prepend(DummyMiddlewarePre::class);
        $stack->insertBefore(DummyMiddlewareC::class, DummyMiddlewareB::class);
        $stack->insertAfter(DummyMiddlewareC::class, DummyMiddlewareAfter::class);
        $stack->remove(DummyMiddlewareD::class);

        self::assertSame([
            DummyMiddlewarePre::class,
            DummyMiddlewareA::class,
            DummyMiddlewareB::class,
            DummyMiddlewareC::class,
            DummyMiddlewareAfter::class,
        ], $stack->all());
    }

    public function testInsertBeforeThrowsWhenTargetMissing(): void
    {
        $stack = new MiddlewareStack([DummyMiddlewareA::class]);

        $this->expectException(\InvalidArgumentException::class);
        $stack->insertBefore(DummyMiddlewareC::class, DummyMiddlewareB::class);
    }

    public function testInsertAfterThrowsWhenTargetMissing(): void
    {
        $stack = new MiddlewareStack([DummyMiddlewareA::class]);

        $this->expectException(\InvalidArgumentException::class);
        $stack->insertAfter(DummyMiddlewareC::class, DummyMiddlewareB::class);
    }
}

final class DummyMiddlewarePre {}
final class DummyMiddlewareA {}
final class DummyMiddlewareB {}
final class DummyMiddlewareC {}
final class DummyMiddlewareD {}
final class DummyMiddlewareAfter {}
