<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Config\Definition;

abstract class AbstractConfigDefinition implements ConfigDefinitionInterface
{
    /**
     * @var array<mixed>
     */
    protected array $data = [];

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * @param array<mixed> $data
     */
    final public static function fromArrayData(array $data): static
    {
        /** @var static $definition */
        $definition = (new \ReflectionClass(static::class))->newInstanceWithoutConstructor();
        $definition->data = self::normalizeArray($data, static::class);

        return $definition;
    }

    protected function set(string $path, mixed $value): static
    {
        $segments = explode('.', $path);
        $ref = &$this->data;

        foreach ($segments as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }

            $ref = &$ref[$segment];
        }

        $ref = $value;

        return $this;
    }

    protected function append(string $path, mixed $value): static
    {
        $segments = explode('.', $path);
        $ref = &$this->data;

        foreach ($segments as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }

            $ref = &$ref[$segment];
        }

        $ref[] = $value;

        return $this;
    }

    /**
     * @param array<mixed> $data
     * @return array<mixed>
     */
    private static function normalizeArray(array $data, string $path): array
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            $childPath = $path . '.' . (string) $key;
            $normalized[$key] = self::normalizeValue($value, $childPath);
        }

        return $normalized;
    }

    private static function normalizeValue(mixed $value, string $path): mixed
    {
        if (is_array($value)) {
            return self::normalizeArray($value, $path);
        }

        if (
            is_string($value)
            || is_int($value)
            || is_float($value)
            || is_bool($value)
            || $value === null
        ) {
            return $value;
        }

        throw new \InvalidArgumentException(sprintf(
            'Config definition payload "%s" contains unsupported value of type "%s" at "%s".',
            static::class,
            get_debug_type($value),
            $path,
        ));
    }
}
