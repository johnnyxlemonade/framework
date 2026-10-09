<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component;

use Lemonade\Framework\Container\ContainerInterface;
use RuntimeException;

/**
 * Maps view-facing component names to container services and resolves them on demand.
 *
 * Registrations may be extended or replaced before a component is resolved.
 */
final class ComponentRegistry
{
    /**
     * Stores component names and the service classes resolved for those names.
     *
     * @var array<string, class-string>
     */
    private array $components = [];

    /**
     * Initializes the registry with the container used for lazy component resolution.
     */
    public function __construct(
        private readonly ContainerInterface $container,
    ) {
    }

    /**
     * Registers or replaces the service class resolved for a component name.
     *
     * @param class-string $componentClass
     */
    public function register(string $name, string $componentClass): void
    {
        $this->components[$name] = $componentClass;
    }

    /**
     * Reports whether a component name is currently registered.
     */
    public function has(string $name): bool
    {
        return isset($this->components[$name]);
    }

    /**
     * Resolves a registered component and optionally verifies its expected implementation type.
     *
     * @template T of object
     *
     * @param class-string<T>|null $expectedClass
     *
     * @return ($expectedClass is class-string<T> ? T : object)
     *
     * @throws RuntimeException When the name is unregistered, cannot resolve to an object, or violates the expected type.
     */
    public function get(string $name, ?string $expectedClass = null): object
    {
        if (!isset($this->components[$name])) {
            throw new RuntimeException(sprintf(
                'Component [%s] is not registered.',
                $name,
            ));
        }

        $component = $this->container->get($this->components[$name]);

        if (!is_object($component)) {
            throw new RuntimeException(sprintf(
                'Component [%s] must resolve to object, %s given.',
                $name,
                get_debug_type($component),
            ));
        }

        if ($expectedClass !== null && !class_exists($expectedClass) && !interface_exists($expectedClass)) {
            throw new RuntimeException(sprintf(
                'Expected component class [%s] does not exist.',
                $expectedClass,
            ));
        }

        if ($expectedClass !== null && !$component instanceof $expectedClass) {
            throw new RuntimeException(sprintf(
                'Component [%s] must be instance of [%s], %s given.',
                $name,
                $expectedClass,
                get_debug_type($component),
            ));
        }

        return $component;
    }

    /**
     * Resolves the registered breadcrumb component.
     */
    public function breadcrumb(): Breadcrumb\BreadcrumbComponent
    {
        return $this->get('breadcrumb', Breadcrumb\BreadcrumbComponent::class);
    }

    /**
     * Resolves the registered request-aware pagination component.
     */
    public function pagination(): Pagination\PaginationComponent
    {
        return $this->get('pagination', Pagination\PaginationComponent::class);
    }

    /**
     * Resolves the registered metadata component.
     */
    public function meta(): Meta\MetaComponent
    {
        return $this->get('meta', Meta\MetaComponent::class);
    }

    /**
     * Returns the current component-name to service-class mapping.
     *
     * @return array<string, class-string>
     */
    public function all(): array
    {
        return $this->components;
    }
}
