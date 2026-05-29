<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Middleware;

use Lemonade\Framework\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;

final class MiddlewareResolver
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    /**
     * @param list<class-string> $middlewareClasses
     * @return list<MiddlewareInterface>
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
