<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Middleware;

use Lemonade\Framework\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;

/**
 * Resolves middleware class names through the application container in declared order.
 */
final readonly class MiddlewareResolver
{
    /**
     * Uses the active container scope for middleware resolution.
     */
    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    /**
     * Resolves every class and rejects services that do not implement PSR-15 middleware.
     *
     * @param list<class-string> $middlewareClasses
     * @return list<MiddlewareInterface>
     *
     * @throws \RuntimeException When a resolved service is not middleware
     */
    public function resolve(array $middlewareClasses): array
    {
        $resolved = [];

        foreach ($middlewareClasses as $middlewareClass) {
            $middleware = $this->container->get($middlewareClass);

            if (!$middleware instanceof MiddlewareInterface) {
                throw new \RuntimeException(sprintf(
                    'Target class "%s" is not a valid middleware.',
                    $middlewareClass,
                ));
            }

            $resolved[] = $middleware;
        }

        return $resolved;
    }
}
