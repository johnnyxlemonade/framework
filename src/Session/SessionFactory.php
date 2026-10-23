<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Http\Request\ResolvedRequestContext;
use Lemonade\Framework\Session\Config\SessionConfig;
use Lemonade\Framework\Session\Contract\SessionInterface;
use Lemonade\Framework\Session\Exception\UnsupportedSessionDriverException;
use Lemonade\Framework\Session\Native\NativeSession;
use Lemonade\Framework\Session\Storage\DatabaseSessionStorage;
use Lemonade\Framework\Session\Storage\FileSessionStorage;
use Lemonade\Framework\Session\Storage\NativeSessionStorage;
use Lemonade\Framework\Session\Storage\RedisSessionStorage;
use Lemonade\Framework\Session\Storage\SessionCookie;
use Lemonade\Framework\Session\Storage\SessionStorageInterface;

/**
 * Creates independent session instances from resolved framework session configuration.
 *
 * Database connectivity is resolved only when the selected driver requires it.
 */
final readonly class SessionFactory
{
    /**
     * @var list<string>
     */
    private const array SUPPORTED_DRIVERS = [
        'native',
        'file',
        'database',
        'redis',
    ];

    /**
     * Defines the application path context and lazy service resolution boundary.
     */
    public function __construct(
        private ApplicationContext $context,
        private ContainerInterface $container,
        private ?ResolvedRequestContext $requestContext = null,
    ) {
    }

    /**
     * Builds a session with the configured storage, cookie identity and retention settings.
     *
     * @throws UnsupportedSessionDriverException when the selected driver is not supported
     */
    public function create(SessionConfig $config): SessionInterface
    {
        return new NativeSession($this->createStorage($config));
    }

    /**
     * Builds the configured persistence backend without exposing it as a container service.
     */
    private function createStorage(SessionConfig $config): SessionStorageInterface
    {
        $driver = $config->driver();
        $cookieConfig = $config->cookie();
        $cookieName = $cookieConfig->name();
        $lifetime = $config->lifetime();
        $cookie = new SessionCookie(
            name: $cookieName,
            lifetimeSeconds: $lifetime,
            sameSite: $cookieConfig->sameSite(),
            secure: $cookieConfig->secure(),
            requestContext: $this->requestContext,
        );

        return match ($driver) {
            'native' => new NativeSessionStorage(
                cookieName: $cookieName,
                lifetimeSeconds: $lifetime,
                savePath: $this->context->resolveSessionPath($config->native()->path()),
                cookie: $cookie,
            ),

            'file' => new FileSessionStorage(
                directory: $this->context->resolveSessionPath($config->file()->path()),
                lifetimeSeconds: $lifetime,
                cookieName: $cookieName,
                cookie: $cookie,
            ),

            'database' => new DatabaseSessionStorage(
                connection: $this->container->get(ConnectionInterface::class),
                table: $config->database()->table(),
                lifetimeSeconds: $lifetime,
                cookieName: $cookieName,
                cookie: $cookie,
            ),

            'redis' => new RedisSessionStorage(
                host: $config->redis()->host(),
                port: $config->redis()->port(),
                database: $config->redis()->database(),
                password: $config->redis()->password(),
                prefix: $config->redis()->prefix(),
                lifetimeSeconds: $lifetime,
                cookieName: $cookieName,
                timeout: $config->redis()->timeout(),
                cookie: $cookie,
            ),

            default => throw UnsupportedSessionDriverException::forDriver(
                $driver,
                self::SUPPORTED_DRIVERS,
            ),
        };
    }
}
