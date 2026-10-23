<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Session;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Session\Config\SessionConfig;
use Lemonade\Framework\Session\Config\SessionConfigDefinition;
use Lemonade\Framework\Session\Config\SessionConfigResolver;
use Lemonade\Framework\Session\Config\SessionCookieConfig;
use Lemonade\Framework\Session\Config\SessionDatabaseConfig;
use Lemonade\Framework\Session\Config\SessionFileConfig;
use Lemonade\Framework\Session\Config\SessionNativeConfig;
use Lemonade\Framework\Session\Config\SessionRedisConfig;
use Lemonade\Framework\Session\Contract\SessionInterface;
use Lemonade\Framework\Session\Cookie\SessionCookieSameSite;
use Lemonade\Framework\Session\Exception\UnsupportedSessionDriverException;
use Lemonade\Framework\Session\Exception\SessionStorageException;
use Lemonade\Framework\Session\Native\NativeSession;
use Lemonade\Framework\Session\SessionFactory;
use Lemonade\Framework\Session\Storage\DatabaseSessionStorage;
use Lemonade\Framework\Session\Storage\FileSessionStorage;
use Lemonade\Framework\Session\Storage\NativeSessionStorage;
use Lemonade\Framework\Session\Storage\RedisSessionStorage;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SessionFactoryTest extends TestCase
{
    public function testItCreatesNativeSessionWithNativeStorageConfiguration(): void
    {
        $container = $this->containerWithoutDatabaseResolution();
        $session = $this->factory($container)->create($this->config('native'));

        self::assertInstanceOf(NativeSession::class, $session);

        $storage = $this->storageFrom($session);

        self::assertInstanceOf(NativeSessionStorage::class, $storage);
        self::assertSame('CONSUMER_SESSION', $this->readPrivateProperty($storage, 'cookieName'));
        self::assertSame(1800, $this->readPrivateProperty($storage, 'lifetimeSeconds'));
        self::assertSame(
            '/srv/application/storage/writable/native-sessions',
            $this->readPrivateProperty($storage, 'savePath'),
        );
        $this->assertStrictSameSiteCookie($storage);
    }

    public function testItCreatesNativeSessionWithFileStorageConfiguration(): void
    {
        $container = $this->containerWithoutDatabaseResolution();
        $session = $this->factory($container)->create($this->config('file'));

        self::assertInstanceOf(NativeSession::class, $session);

        $storage = $this->storageFrom($session);

        self::assertInstanceOf(FileSessionStorage::class, $storage);
        self::assertSame(
            '/srv/application/storage/writable/file-sessions',
            $this->readPrivateProperty($storage, 'directory'),
        );
        self::assertSame(1800, $this->readPrivateProperty($storage, 'lifetimeSeconds'));
        self::assertSame('CONSUMER_SESSION', $this->readPrivateProperty($storage, 'cookieName'));
        $this->assertStrictSameSiteCookie($storage);
    }

    public function testItPreservesSafeAbsoluteCustomFileStorageOutsidePublicRoot(): void
    {
        $session = $this->factory($this->containerWithoutDatabaseResolution())
            ->create($this->config('file', '/var/lib/lemonade/private-sessions'));
        $storage = $this->storageFrom($session);

        self::assertInstanceOf(FileSessionStorage::class, $storage);
        self::assertSame(
            '/var/lib/lemonade/private-sessions',
            $this->readPrivateProperty($storage, 'directory'),
        );
    }

    public function testItRejectsFileStorageInsidePublicWebroot(): void
    {
        $this->expectException(SessionStorageException::class);
        $this->expectExceptionMessage('must be separated from the public web root');

        $this->factory($this->containerWithoutDatabaseResolution())
            ->create($this->config('file', '/srv/application/public/sessions'));
    }

    public function testDefaultFilePathWorksWithoutAdditionalFileConfiguration(): void
    {
        $config = (new SessionConfigResolver())->resolve(
            SessionConfigDefinition::create()->driver('file'),
        );
        $session = $this->factory($this->containerWithoutDatabaseResolution())->create($config);
        $storage = $this->storageFrom($session);

        self::assertInstanceOf(FileSessionStorage::class, $storage);
        self::assertSame(
            '/srv/application/storage/writable/sessions',
            $this->readPrivateProperty($storage, 'directory'),
        );
    }

    public function testItCreatesDatabaseStorageAndResolvesItsConnectionLazily(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $container = $this->createMock(ContainerInterface::class);
        $container
            ->expects(self::once())
            ->method('get')
            ->with(ConnectionInterface::class)
            ->willReturn($connection);

        $session = $this->factory($container)->create($this->config('database'));

        self::assertInstanceOf(NativeSession::class, $session);

        $storage = $this->storageFrom($session);

        self::assertInstanceOf(DatabaseSessionStorage::class, $storage);
        self::assertSame($connection, $this->readPrivateProperty($storage, 'connection'));
        self::assertSame('consumer_sessions', $this->readPrivateProperty($storage, 'table'));
        self::assertSame(1800, $this->readPrivateProperty($storage, 'lifetimeSeconds'));
        self::assertSame('CONSUMER_SESSION', $this->readPrivateProperty($storage, 'cookieName'));
        $this->assertStrictSameSiteCookie($storage);
    }

    public function testItCreatesRedisStorageWithoutResolvingDatabaseConnection(): void
    {
        $container = $this->containerWithoutDatabaseResolution();
        $session = $this->factory($container)->create($this->config('redis'));

        self::assertInstanceOf(NativeSession::class, $session);

        $storage = $this->storageFrom($session);

        self::assertInstanceOf(RedisSessionStorage::class, $storage);
        self::assertSame('redis.internal', $this->readPrivateProperty($storage, 'host'));
        self::assertSame(6380, $this->readPrivateProperty($storage, 'port'));
        self::assertSame(4, $this->readPrivateProperty($storage, 'database'));
        self::assertSame('secret', $this->readPrivateProperty($storage, 'password'));
        self::assertSame('consumer:', $this->readPrivateProperty($storage, 'prefix'));
        self::assertSame(1800, $this->readPrivateProperty($storage, 'lifetimeSeconds'));
        self::assertSame('CONSUMER_SESSION', $this->readPrivateProperty($storage, 'cookieName'));
        self::assertSame(1.25, $this->readPrivateProperty($storage, 'timeout'));
        $this->assertStrictSameSiteCookie($storage);
    }

    public function testItRejectsUnsupportedDriverWithCanonicalSupportedDriverList(): void
    {
        $this->expectException(UnsupportedSessionDriverException::class);
        $this->expectExceptionMessage(
            'Unsupported session driver "unsupported". Supported drivers are: native, file, database, redis.',
        );

        $this->factory($this->containerWithoutDatabaseResolution())
            ->create($this->config('unsupported'));
    }

    /**
     * @return ContainerInterface&MockObject
     */
    private function containerWithoutDatabaseResolution(): ContainerInterface
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::never())->method('get');

        return $container;
    }

    private function factory(ContainerInterface $container): SessionFactory
    {
        return new SessionFactory(
            new ApplicationContext(
                Environment::Testing,
                new Path('/srv/application', '/srv/application/public'),
                DebugMode::disabled(),
            ),
            $container,
        );
    }

    private function config(string $driver, string $filePath = 'file-sessions'): SessionConfig
    {
        return new SessionConfig(
            driver: $driver,
            lifetime: 1800,
            cookie: new SessionCookieConfig(
                name: 'CONSUMER_SESSION',
                sameSite: SessionCookieSameSite::Strict,
            ),
            native: new SessionNativeConfig('native-sessions'),
            file: new SessionFileConfig($filePath),
            database: new SessionDatabaseConfig('consumer_sessions'),
            redis: new SessionRedisConfig(
                host: 'redis.internal',
                port: 6380,
                database: 4,
                password: 'secret',
                prefix: 'consumer:',
                timeout: 1.25,
            ),
        );
    }

    private function assertStrictSameSiteCookie(object $storage): void
    {
        $cookie = $this->readPrivateProperty($storage, 'cookie');

        self::assertIsObject($cookie);
        self::assertSame(
            SessionCookieSameSite::Strict,
            $this->readPrivateProperty($cookie, 'sameSite'),
        );
    }

    private function storageFrom(SessionInterface $session): object
    {
        $storage = $this->readPrivateProperty($session, 'storage');

        self::assertIsObject($storage);

        return $storage;
    }

    private function readPrivateProperty(object $object, string $property): mixed
    {
        $reflection = new \ReflectionObject($object);
        $instanceProperty = $reflection->getProperty($property);

        return $instanceProperty->getValue($object);
    }
}
