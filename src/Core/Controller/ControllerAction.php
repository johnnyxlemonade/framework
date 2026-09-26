<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Controller;

use ReflectionMethod;

final readonly class ControllerAction
{
    public function __construct(
        private object $controller,
        private ReflectionMethod $method,
        private string $controllerClass,
        private string $name,
    ) {}

    public function controller(): object
    {
        return $this->controller;
    }

    public function method(): ReflectionMethod
    {
        return $this->method;
    }

    public function controllerClass(): string
    {
        return $this->controllerClass;
    }

    public function name(): string
    {
        return $this->name;
    }
}
