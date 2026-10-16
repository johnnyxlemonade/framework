<?php

declare(strict_types=1);

namespace Lemonade\Framework\RateLimit;

use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config\AppConfig;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Database\Database;

/**
 * Registers the database-backed fixed-window limiter as a framework infrastructure service.
 */
final class RateLimitServiceProvider implements ServiceProviderInterface
{
    /**
     * Binds the generic limiter with application configuration supplied through DI.
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $container->singleton(RateLimiterInterface::class, static function (ContainerInterface $container): DatabaseRateLimiter {
            $secret = $container->get(AppConfig::class)->secret();
            if ($secret === null || $secret === '') {
                throw new \LogicException('APP_SECRET must be configured before using the database rate limiter.');
            }

            return new DatabaseRateLimiter(
                database: $container->get(Database::class),
                secret: $secret,
            );
        });
        $container->alias(DatabaseRateLimiter::class, RateLimiterInterface::class);
    }
}
