<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Core\Controller;

use Lemonade\Framework\Core\Controller\ControllerActionInspector;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ControllerActionInspectorTest extends TestCase
{
    public function testInspectsPublicAction(): void
    {
        $controller = new ControllerActionInspectorSubject();

        $action = (new ControllerActionInspector())->inspect(
            $controller,
            ControllerActionInspectorSubject::class,
            'show',
        );

        self::assertSame($controller, $action->controller());
        self::assertSame(ControllerActionInspectorSubject::class, $action->controllerClass());
        self::assertSame('show', $action->name());
        self::assertTrue($action->method()->isPublic());
    }

    public function testRejectsNonPublicAction(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must be public');

        (new ControllerActionInspector())->inspect(
            new ControllerActionInspectorSubject(),
            ControllerActionInspectorSubject::class,
            'hidden',
        );
    }
}

final class ControllerActionInspectorSubject
{
    public function show(): string
    {
        return 'show';
    }

    protected function hidden(): string
    {
        return 'hidden';
    }
}
