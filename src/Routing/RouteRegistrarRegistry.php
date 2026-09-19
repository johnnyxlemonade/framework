<?php

declare(strict_types=1);

namespace Lemonade\Framework\Routing;

use LogicException;
use RuntimeException;
use Throwable;

/**
 * Collects provider-owned route registrars until routing bootstrap is finalized.
 */
final class RouteRegistrarRegistry
{
    /**
     * @var array<string, RouteRegistrarInterface>
     */
    private array $registrars = [];

    private bool $frozen = false;

    public function register(RouteRegistrarInterface $registrar): void
    {
        $this->assertMutable();

        $id = trim($registrar->id());
        if ($id === '') {
            throw new LogicException('Route registrar ID must not be empty.');
        }

        if (isset($this->registrars[$id])) {
            throw new LogicException(sprintf('Route registrar "%s" is already registered.', $id));
        }

        $this->registrars[$id] = $registrar;
    }

    public function registerRoutes(Router $router): void
    {
        $this->assertMutable();

        $registrars = $this->registrars;
        uasort($registrars, static function (RouteRegistrarInterface $left, RouteRegistrarInterface $right): int {
            $priority = $left->priority() <=> $right->priority();

            return $priority !== 0 ? $priority : strcmp($left->id(), $right->id());
        });

        foreach ($registrars as $registrar) {
            try {
                $registrar->registerRoutes($router);
            } catch (Throwable $exception) {
                throw new RuntimeException(sprintf(
                    'Route registrar "%s" failed: %s',
                    $registrar->id(),
                    $exception->getMessage(),
                ), 0, $exception);
            }
        }
    }

    public function freeze(): void
    {
        $this->frozen = true;
    }

    public function isFrozen(): bool
    {
        return $this->frozen;
    }

    private function assertMutable(): void
    {
        if ($this->frozen) {
            throw new LogicException('Route registrar registry is frozen.');
        }
    }
}
