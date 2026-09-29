<?php

declare(strict_types=1);

namespace Lemonade\Framework\Routing;

/**
 * An explicit controller method endpoint.
 *
 * The controller class is kept as its explicit fully-qualified class name.
 */
final readonly class ControllerAction
{
    private function __construct(
        private string $controllerClass,
        private string $method,
    ) {}

    public static function for(string $controllerClass, string $method): self
    {
        $controllerClass = trim($controllerClass);
        $method = trim($method);

        if ($controllerClass === '') {
            throw new \InvalidArgumentException('Controller action controller class must not be empty.');
        }

        if ($method === '') {
            throw new \InvalidArgumentException('Controller action method must not be empty.');
        }

        return new self($controllerClass, $method);
    }

    public function controllerClass(): string
    {
        return $this->controllerClass;
    }

    public function method(): string
    {
        return $this->method;
    }
}
