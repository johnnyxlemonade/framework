<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Observability\Benchmark\Benchmark;
use Lemonade\Framework\Routing\RouteMatch;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use RuntimeException;

final class ControllerResolver
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    public function handle(RouteMatch $match, ServerRequestInterface $request): PsrResponseInterface
    {
        // Bind the current request for this dispatch cycle so constructor DI
        // resolves the live request instance instead of a stale previous one.
        $this->container->set(ServerRequestInterface::class, $request);

        $controllerClass = $match->controller();
        $action = $match->action();
        $controllerClass = trim($controllerClass);
        if ($controllerClass === '') {
            throw new RuntimeException('Resolved controller class must be a non-empty string.');
        }
        /** @var non-empty-string $controllerClass */

        $this->markBenchmark('controller_resolve_start');
        $controller = $this->container->get($controllerClass);
        if (!is_object($controller)) {
            throw new RuntimeException(sprintf(
                'Resolved controller "%s" must be an object.',
                $controllerClass,
            ));
        }

        if ($controller instanceof AbstractController) {
            /** @var ResponseFactoryInterface $responseFactory */
            $responseFactory = $this->container->get(ResponseFactoryInterface::class);
            /** @var StreamFactoryInterface $streamFactory */
            $streamFactory = $this->container->get(StreamFactoryInterface::class);
            $controller->setControllerContext($request, $responseFactory, $streamFactory);
        }

        $this->markBenchmark('controller_resolved');

        if (!method_exists($controller, $action)) {
            throw new RuntimeException(sprintf(
                'Action "%s::%s" not found.',
                $controllerClass,
                $action,
            ));
        }

        $method = new ReflectionMethod($controller, $action);
        $args = [];

        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (
                $type instanceof ReflectionNamedType
                && !$type->isBuiltin()
                && $type->getName() === ServerRequestInterface::class
            ) {
                $args[] = $request;
                continue;
            }

            if (array_key_exists($parameter->getName(), $match->params())) {
                $args[] = $this->castScalarParam(
                    $match->params()[$parameter->getName()],
                    $type,
                    $parameter->getName(),
                );

                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $args[] = $parameter->getDefaultValue();
                continue;
            }

            throw new RuntimeException(sprintf(
                'Cannot resolve action parameter "%s" in %s::%s.',
                $parameter->getName(),
                $controllerClass,
                $action,
            ));
        }

        $this->markBenchmark('controller_action_start');
        $result = $method->invokeArgs($controller, $args);
        $this->markBenchmark('controller_action_finished');

        return $this->normalizeResultToPsrResponse($result);
    }

    private function castScalarParam(mixed $value, ?ReflectionType $type, string $paramName): mixed
    {
        if (!$type instanceof ReflectionNamedType || !$type->isBuiltin()) {
            return $value;
        }

        return match ($type->getName()) {
            'int' => $this->toInt($value, $paramName),
            'float' => $this->toFloat($value, $paramName),
            'bool' => $this->toBool($value, $paramName),
            'string' => $this->toString($value, $paramName),
            default => $value,
        };
    }

    private function toInt(mixed $value, string $paramName): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw new RuntimeException(sprintf(
            'Invalid value for parameter "%s". Expected integer, got "%s".',
            $paramName,
            $this->formatInvalidValue($value),
        ));
    }

    private function toFloat(mixed $value, string $paramName): float
    {
        if (is_float($value)) {
            return $value;
        }

        if (is_int($value)) {
            return (float) $value;
        }

        if (is_string($value) && preg_match('/^-?\d+(?:\.\d+)?$/', $value) === 1) {
            return (float) $value;
        }

        throw new RuntimeException(sprintf(
            'Invalid value for parameter "%s". Expected float, got "%s".',
            $paramName,
            $this->formatInvalidValue($value),
        ));
    }

    private function toBool(mixed $value, string $paramName): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === 1 || $value === '1' || $value === 'true') {
            return true;
        }

        if ($value === 0 || $value === '0' || $value === 'false') {
            return false;
        }

        throw new RuntimeException(sprintf(
            'Invalid value for parameter "%s". Expected boolean, got "%s".',
            $paramName,
            $this->formatInvalidValue($value),
        ));
    }

    private function toString(mixed $value, string $paramName): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value) || $value instanceof \Stringable) {
            return (string) $value;
        }

        throw new RuntimeException(sprintf(
            'Invalid value for parameter "%s". Expected string-compatible value, got "%s".',
            $paramName,
            $this->formatInvalidValue($value),
        ));
    }

    private function formatInvalidValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return get_debug_type($value);
    }

    private function normalizeResultToPsrResponse(mixed $result): PsrResponseInterface
    {
        if ($result instanceof PsrResponseInterface) {
            $this->markBenchmark('response_created');
            return $result;
        }

        if (is_scalar($result) || $result === null || $result instanceof \Stringable) {
            /** @var ResponseFactoryInterface $responseFactory */
            $responseFactory = $this->container->get(ResponseFactoryInterface::class);
            /** @var StreamFactoryInterface $streamFactory */
            $streamFactory = $this->container->get(StreamFactoryInterface::class);

            $response = $responseFactory
                ->createResponse(200)
                ->withHeader('Content-Type', 'text/html; charset=UTF-8')
                ->withBody($streamFactory->createStream((string) $result));
            $this->markBenchmark('response_created');

            return $response;
        }

        throw new RuntimeException(sprintf(
            'Controller action result must be scalar|stringable|null or %s, %s given.',
            PsrResponseInterface::class,
            get_debug_type($result),
        ));
    }

    private function markBenchmark(string $name): void
    {
        if (!$this->container->isBound(Benchmark::class)) {
            return;
        }

        $benchmark = $this->container->get(Benchmark::class);
        $run = $benchmark->current();
        if ($run === null) {
            return;
        }

        $run->mark($name);
    }
}
