<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Config;

use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionInterface;

/**
 * Collects component registrations contributed by an application or module.
 *
 * Later definitions may replace a registration with the same name during resolution.
 */
final class ComponentConfigDefinition implements ConfigDefinitionInterface
{
    /**
     * @var array<string, class-string>
     */
    private array $components = [];

    /**
     * Starts an empty component-registration definition.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Identifies the configuration section handled by this definition.
     */
    public static function moduleKey(): string
    {
        return 'components';
    }

    /**
     * Registers a component class under the supplied application-facing name.
     *
     * @param class-string $componentClass
     */
    public function component(string $name, string $componentClass): self
    {
        $this->components[$name] = $componentClass;

        return $this;
    }

    /**
     * Registers all supplied component mappings while preserving this definition's fluent API.
     *
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
     * Exposes the registrations for framework configuration resolution.
     *
     * @return array<string, class-string>
     */
    public function toArray(): array
    {
        return $this->components;
    }
}
