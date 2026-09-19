<?php

declare(strict_types=1);

namespace Lemonade\Framework\Routing;

/**
 * Declares routes owned by a service provider or independently installable capability.
 *
 * Registrars are collected during provider registration and executed by the framework
 * only after application composition routes have been registered.
 */
interface RouteRegistrarInterface
{
    /**
     * Returns the stable, globally unique registrar identifier.
     */
    public function id(): string;

    /**
     * Returns the deterministic route registration priority; lower values run first.
     */
    public function priority(): int;

    /**
     * Registers this capability's routes through the normal router API.
     */
    public function registerRoutes(Router $router): void;
}
