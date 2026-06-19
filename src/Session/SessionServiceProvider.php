<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Session\Config\SessionConfig;
use Lemonade\Framework\Session\Config\SessionConfigDefinition;
use Lemonade\Framework\Session\Config\SessionConfigResolver;
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
        $container->singleton(SessionConfigResolver::class, SessionConfigResolver::class);
        $container->singleton(SessionConfig::class, static function (ContainerInterface $container): SessionConfig {
            return $container
                ->get(SessionConfigResolver::class)
                ->resolve(...$container->get(ConfigDefinitionRegistry::class)->typedEntriesFor(
                    SessionConfigDefinition::moduleKey(),
                    SessionConfigDefinition::class,
                ));
        });
        $container->singleton(SessionStorageInterface::class, static function (ContainerInterface $container): SessionStorageInterface {
            $config = $container->get(SessionConfig::class);
            $context = $container->get(ApplicationContext::class);

            $driver = $config->driver;
            $cookieName = $config->cookie;
            $lifetime = $config->lifetime;

            return match ($driver) {
                'native' => new NativeSessionStorage(
                    cookieName: $cookieName,
                    lifetimeSeconds: $lifetime,
                    savePath: $context->resolveSessionPath($config->native->path),
                ),

                'file' => new FileSessionStorage(
                    directory: $context->resolveSessionPath($config->file->path),
                    lifetimeSeconds: $lifetime,
                    cookieName: $cookieName,
                ),

                'database' => new DatabaseSessionStorage(
                    connection: $container->get(ConnectionInterface::class),
                    table: $config->database->table,
                    lifetimeSeconds: $lifetime,
                    cookieName: $cookieName,
                ),

                'redis' => new RedisSessionStorage(
                    host: $config->redis->host,
                    port: $config->redis->port,
                    database: $config->redis->database,
                    password: $config->redis->password,
                    prefix: $config->redis->prefix,
                    lifetimeSeconds: $lifetime,
                    cookieName: $cookieName,
                    timeout: $config->redis->timeout,
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

}
