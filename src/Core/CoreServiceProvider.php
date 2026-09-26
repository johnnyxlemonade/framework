<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core;

use DateTimeZone;
use Lemonade\Framework\Clock\ClockInterface;
use Lemonade\Framework\Clock\SystemClock;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config\AppConfig;
use Lemonade\Framework\Core\Diagnostics\ExceptionLogger;
use Lemonade\Framework\Support\BaseUrlResolver;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use RuntimeException;
use Throwable;

/**
 * Registers the foundational framework services required by the core runtime.
 *
 * The provider wires PSR-17 interfaces, controller resolution helpers, base
 * URL resolution, framework metadata, exception logging, and system clock
 * services.
 */
final class CoreServiceProvider implements ServiceProviderInterface
{
    /**
     * Registers core framework services as singletons in the container.
     *
     * Nyholm's PSR-17 factory is exposed through all PSR-17 interfaces used by
     * the framework through their typed interfaces. The provider also registers
     * controller and URL helpers, framework metadata, exception logger, and a
     * system clock resolved from the application timezone. When the timezone
     * is missing or empty, the system clock falls back to its default timezone
     * behaviour.
     *
     * @throws RuntimeException If the configured application timezone is invalid.
     */
    public function register(ContainerInterface $container): void
    {
        /*
         * PSR-7 / PSR-17 factories.
         *
         * Nyholm's Psr17Factory implements all PSR-17 factory interfaces used by the framework.
         */
        $container->singleton(ResponseFactoryInterface::class, static fn(ContainerInterface $container): Psr17Factory => $container->get(Psr17Factory::class));
        $container->singleton(RequestFactoryInterface::class, static fn(ContainerInterface $container): Psr17Factory => $container->get(Psr17Factory::class));
        $container->singleton(ServerRequestFactoryInterface::class, static fn(ContainerInterface $container): Psr17Factory => $container->get(Psr17Factory::class));
        $container->singleton(StreamFactoryInterface::class, static fn(ContainerInterface $container): Psr17Factory => $container->get(Psr17Factory::class));
        $container->singleton(UploadedFileFactoryInterface::class, static fn(ContainerInterface $container): Psr17Factory => $container->get(Psr17Factory::class));
        $container->singleton(UriFactoryInterface::class, static fn(ContainerInterface $container): Psr17Factory => $container->get(Psr17Factory::class));

        /*
         * Core framework utilities.
         */
        $container->scoped(ControllerResolver::class, ControllerResolver::class);
        $container->singleton(BaseUrlResolver::class, BaseUrlResolver::class);
        $container->singleton(FrameworkInfo::class, FrameworkInfo::class);
        $container->singleton(ExceptionLogger::class, ExceptionLogger::class);
        $timezone = $this->resolveClockTimezone(
            $container->get(AppConfig::class),
        );
        $container->singleton(ClockInterface::class, new SystemClock($timezone));

    }

    private function resolveClockTimezone(AppConfig $config): ?DateTimeZone
    {
        $value = $config->timezone;
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return new DateTimeZone($value);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                sprintf('Invalid configured timezone in app.timezone: "%s".', $value),
                0,
                $exception,
            );
        }
    }
}
