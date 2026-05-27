<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Session\Contract\SessionInterface;
use Lemonade\Framework\Session\Exception\UnsupportedSessionDriverException;
use Lemonade\Framework\Session\Flash\FlashBagInterface;
use Lemonade\Framework\Session\Flash\SessionFlashBag;
use Lemonade\Framework\Session\Native\NativeSession;
use Lemonade\Framework\Session\Storage\DatabaseSessionStorage;
use Lemonade\Framework\Session\Storage\FileSessionStorage;
use Lemonade\Framework\Session\Storage\NativeSessionStorage;
use Lemonade\Framework\Session\Storage\RedisSessionStorage;
use Lemonade\Framework\Session\Storage\SessionStorageInterface;

final class SessionServiceProvider implements ServiceProviderInterface
{
    /**
     * @var list<string>
     */
    private const SUPPORTED_DRIVERS = [
        'native',
        'file',
        'database',
        'redis',
    ];

    public function register(ContainerInterface $container): void
    {
        $container->singleton(SessionStorageInterface::class, static function (ContainerInterface $container): SessionStorageInterface {
            $config = $container->get(Config::class);
            $context = $container->get(ApplicationContext::class);

            $driver = strtolower($config->string('session.driver', 'native') ?? 'native');
            $cookieName = $config->string('session.cookie', 'LEMONADE_SESSION') ?? 'LEMONADE_SESSION';
            $lifetime = $config->int('session.lifetime', 7200);

            return match ($driver) {
                'native' => new NativeSessionStorage(
                    cookieName: $cookieName,
                    lifetimeSeconds: $lifetime,
                    savePath: $context->resolveSessionPath($config->string(
                        'session.native.path',
                        'sessions',
                    ) ?? 'sessions'),
                ),

                'file' => new FileSessionStorage(
                    directory: $context->resolveSessionPath($config->string(
                        'session.file.path',
                        'sessions',
                    ) ?? 'sessions'),
                    lifetimeSeconds: $lifetime,
                    cookieName: $cookieName,
                ),

                'database' => new DatabaseSessionStorage(
                    connection: $container->get(ConnectionInterface::class),
                    table: $config->string('session.database.table', 'sessions') ?? 'sessions',
                    lifetimeSeconds: $lifetime,
                    cookieName: $cookieName,
                ),

                'redis' => new RedisSessionStorage(
                    host: $config->string('session.redis.host', '127.0.0.1') ?? '127.0.0.1',
                    port: $config->int('session.redis.port', 6379),
                    database: $config->int('session.redis.database', 0),
                    password: self::nullableString($config->get('session.redis.password')),
                    prefix: $config->string('session.redis.prefix', 'sess:') ?? 'sess:',
                    lifetimeSeconds: $lifetime,
                    cookieName: $cookieName,
                    timeout: self::toFloat($config->get('session.redis.timeout'), 2.5),
                ),

                default => throw UnsupportedSessionDriverException::forDriver(
                    $driver,
                    self::SUPPORTED_DRIVERS,
                ),
            };
        });

        $container->singleton(SessionInterface::class, NativeSession::class);
        $container->singleton(NativeSession::class, NativeSession::class);
        $container->singleton('session', SessionInterface::class);

        $container->singleton(FlashBagInterface::class, SessionFlashBag::class);
        $container->singleton(SessionFlashBag::class, SessionFlashBag::class);
        $container->singleton('flash', FlashBagInterface::class);
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private static function toFloat(mixed $value, float $default): float
    {
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return $default;
    }
}
