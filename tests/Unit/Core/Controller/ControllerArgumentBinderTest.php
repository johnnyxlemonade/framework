<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Core\Controller;

use Lemonade\Framework\Core\Controller\ControllerActionInspector;
use Lemonade\Framework\Core\Controller\ControllerArgumentBinder;
use Lemonade\Framework\Routing\RouteMatch;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

final class ControllerArgumentBinderTest extends TestCase
{
    public function testBindsRequestRouteParamAndDefaultValue(): void
    {
        $controller = new ControllerArgumentBinderSubject();
        $action = (new ControllerActionInspector())->inspect(
            $controller,
            ControllerArgumentBinderSubject::class,
            'show',
        );
        $request = (new Psr17Factory())->createServerRequest('GET', '/articles/42');

        $args = (new ControllerArgumentBinder())->bind(
            $action,
            new RouteMatch(ControllerArgumentBinderSubject::class, 'show', ['id' => '42']),
            $request,
        );

        self::assertSame($request, $args[0]);
        self::assertSame(42, $args[1]);
        self::assertSame('default', $args[2]);
    }

    public function testRejectsMissingRequiredArgument(): void
    {
        $controller = new ControllerArgumentBinderSubject();
        $action = (new ControllerActionInspector())->inspect(
            $controller,
            ControllerArgumentBinderSubject::class,
            'required',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot resolve action parameter "id"');

        (new ControllerArgumentBinder())->bind(
            $action,
            new RouteMatch(ControllerArgumentBinderSubject::class, 'required'),
            (new Psr17Factory())->createServerRequest('GET', '/articles'),
        );
    }
}

final class ControllerArgumentBinderSubject
{
    public function show(ServerRequestInterface $request, int $id, string $format = 'default'): void
    {
        unset($request, $id, $format);
    }

    public function required(int $id): void
    {
        unset($id);
    }
}
