<?php

declare(strict_types=1);

namespace Lemonade\Framework\Container;

use Lemonade\Framework\Container\Exception\ScopedContainerClosedException;
use Psr\Log\LoggerInterface;

final class ScopedContainer implements ScopedContainerInterface
{
    /** @var array<string, mixed> */
    private array $instances = [];

    /** @var array<string, object> */
    private array $localInstances = [];

    private bool $closed = false;

    public function __construct(
        private readonly Container $root,
        private readonly ScopeKind $kind,
    ) {
        $this->localInstances[ContainerInterface::class] = $this;
        $this->localInstances[ScopedContainerInterface::class] = $this;
    }

    public function kind(): ScopeKind
    {
        return $this->kind;
    }

    public function close(): void
    {
        $this->instances = [];
        $this->localInstances = [];
        $this->closed = true;
    }

    public function bindScopedInstance(string $id, object $instance): void
    {
        $this->assertOpen();
        $this->localInstances[$id] = $instance;
    }

    public function set(string $id, callable|object|string $concrete): void
    {
        $this->assertOpen();
        $this->root->set($id, $concrete);
    }

    public function singleton(string $id, callable|object|string $concrete): void
    {
        $this->assertOpen();
        $this->root->singleton($id, $concrete);
    }

    public function scoped(string $id, callable|object|string $concrete): void
    {
        $this->assertOpen();
        $this->root->scoped($id, $concrete);
    }

    public function singletonTagged(string $id, callable|object|string $concrete, string ...$tags): void
    {
        $this->assertOpen();
        $this->root->singletonTagged($id, $concrete, ...$tags);
    }

    public function tag(string $serviceId, string $tag): void
    {
        $this->assertOpen();
        $this->root->tag($serviceId, $tag);
    }

    /** @return iterable<string, object> */
    public function tagged(string $tag): iterable
    {
        $this->assertOpen();

        return $this->root->taggedInScope($this, $tag);
    }

    public function setDiagnosticLogger(?LoggerInterface $logger): void
    {
        $this->assertOpen();
        $this->root->setDiagnosticLogger($logger);
    }

    public function has(string $id): bool
    {
        return isset($this->localInstances[$id]) || $this->root->has($id);
    }

    public function isBound(string $id): bool
    {
        return isset($this->localInstances[$id]) || $this->root->isBound($id);
    }

    public function get(string $id): mixed
    {
        $this->assertOpen();

        if (isset($this->localInstances[$id])) {
            return $this->localInstances[$id];
        }

        return $this->root->getInScope($this, $id);
    }

    public function hasInstance(string $id): bool
    {
        return isset($this->instances[$id]);
    }

    public function instance(string $id): mixed
    {
        return $this->instances[$id];
    }

    public function storeInstance(string $id, mixed $instance): void
    {
        $this->instances[$id] = $instance;
    }

    private function assertOpen(): void
    {
        if ($this->closed) {
            throw new ScopedContainerClosedException(sprintf(
                'The %s scope has already been closed.',
                $this->kind->value,
            ));
        }
    }
}
