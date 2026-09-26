<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Controller;

use ReflectionMethod;
use RuntimeException;

final class ControllerActionInspector
{
    public function inspect(object $controller, string $controllerClass, string $action): ControllerAction
    {
        if (!method_exists($controller, $action)) {
            throw new RuntimeException(sprintf(
                'Action "%s::%s" not found.',
                $controllerClass,
                $action,
            ));
        }

        $method = new ReflectionMethod($controller, $action);
        if (!$method->isPublic()) {
            throw new RuntimeException(sprintf(
                'Controller action "%s::%s" must be public.',
                $controllerClass,
                $action,
            ));
        }

        return new ControllerAction($controller, $method, $controllerClass, $action);
    }
}
