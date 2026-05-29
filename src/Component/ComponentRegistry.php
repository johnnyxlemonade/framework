<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component;

use Lemonade\Framework\Container\ContainerInterface;
use RuntimeException;

final class ComponentRegistry
{
    /**
     * @var array<string, class-string>
     */
    private array $components = [];

    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    /**
     * @param class-string $componentClass
     */
    public function register(string $name, string $componentClass): void
    {
        $this->components[$name] = $componentClass;
    }

    public function has(string $name): bool
    {
        return isset($this->components[$name]);
    }

    /**
     * @template T of object
     *
     * @param class-string<T>|null $expectedClass
     *
     * @return ($expectedClass is class-string<T> ? T : object)
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

    public function breadcrumb(): Breadcrumb\BreadcrumbComponent
    {
        return $this->get('breadcrumb', Breadcrumb\BreadcrumbComponent::class);
    }

    public function pagination(): Pagination\PaginationComponent
    {
        return $this->get('pagination', Pagination\PaginationComponent::class);
    }

    public function meta(): Meta\MetaComponent
    {
        return $this->get('meta', Meta\MetaComponent::class);
    }

    /**
     * @return array<string, class-string>
     */
    public function all(): array
    {
        return $this->components;
    }
}
