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
}
