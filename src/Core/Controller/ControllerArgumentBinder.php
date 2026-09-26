<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Controller;

use Lemonade\Framework\Routing\RouteMatch;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionNamedType;
use ReflectionType;
use RuntimeException;

final class ControllerArgumentBinder
{
    /**
     * @return list<mixed>
     */
    public function bind(ControllerAction $action, RouteMatch $match, ServerRequestInterface $request): array
    {
        $args = [];
        $params = $match->params();

        foreach ($action->method()->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (
                $type instanceof ReflectionNamedType
                && !$type->isBuiltin()
                && $type->getName() === ServerRequestInterface::class
            ) {
                $args[] = $request;
                continue;
            }

            if (array_key_exists($parameter->getName(), $params)) {
                $args[] = $this->castScalarParam(
                    $params[$parameter->getName()],
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
                $action->controllerClass(),
                $action->name(),
            ));
        }

        return $args;
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
}
