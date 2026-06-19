<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Config;

use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionInterface;

final class ComponentConfigDefinition implements ConfigDefinitionInterface
{
    /**
     * @var array<string, class-string>
     */
    private array $components = [];

    public static function create(): self
    {
        return new self();
    }

    public static function moduleKey(): string
    {
        return 'components';
    }

    /**
     * @param class-string $componentClass
     */
    public function component(string $name, string $componentClass): self
    {
        $this->components[$name] = $componentClass;

        return $this;
    }

    /**
     * @param array<string, class-string> $components
     */
    public function components(array $components): self
    {
        foreach ($components as $name => $componentClass) {
            $this->component($name, $componentClass);
        }

        return $this;
    }

    /**
     * @return array<string, class-string>
     */
    public function toArray(): array
    {
        return $this->components;
    }
}
