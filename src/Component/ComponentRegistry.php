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

    public function get(string $name): object
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

        return $component;
    }

    public function breadcrumb(): Breadcrumb\BreadcrumbComponent
    {
        /** @var Breadcrumb\BreadcrumbComponent $component */
        $component = $this->get('breadcrumb');

        return $component;
    }

    public function pagination(): Pagination\PaginationComponent
    {
        /** @var Pagination\PaginationComponent $component */
        $component = $this->get('pagination');

        return $component;
    }

    public function meta(): Meta\MetaComponent
    {
        /** @var Meta\MetaComponent $component */
        $component = $this->get('meta');

        return $component;
    }

    /**
     * @return array<string, class-string>
     */
    public function all(): array
    {
        return $this->components;
    }
}
