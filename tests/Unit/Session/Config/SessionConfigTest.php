<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Session\Config;

use InvalidArgumentException;
use Lemonade\Framework\Session\Config\SessionConfig;
use Lemonade\Framework\Session\Config\SessionCookieConfig;
use Lemonade\Framework\Session\Config\SessionDatabaseConfig;
use Lemonade\Framework\Session\Config\SessionFileConfig;
use Lemonade\Framework\Session\Config\SessionNativeConfig;
use Lemonade\Framework\Session\Config\SessionRedisConfig;
use Lemonade\Framework\Session\Cookie\SessionCookieSameSite;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SessionConfigTest extends TestCase
{
    #[DataProvider('invalidDrivers')]
    public function testItRejectsNonCanonicalDrivers(string $driver): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Session driver must be a non-empty canonical lowercase identifier.');

        $this->config(driver: $driver);
    }

    public function testItRejectsNonPositiveLifetime(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Session lifetime must be positive.');

        $this->config(lifetime: 0);
    }

    #[DataProvider('invalidCookieNames')]
    public function testItRejectsInvalidCookieNames(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Session cookie name must contain a letter');

        new SessionCookieConfig($name, SessionCookieSameSite::Lax);
    }

    #[DataProvider('invalidRedisEndpoints')]
    public function testItRejectsInvalidRedisEndpointValues(
        int $port,
        int $database,
        float $timeout,
        string $message,
    ): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        new SessionRedisConfig(
            host: '127.0.0.1',
            port: $port,
            database: $database,
            password: null,
            prefix: 'sess:',
            timeout: $timeout,
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidDrivers(): iterable
    {
        yield 'empty' => [''];
        yield 'uppercase' => ['Native'];
        yield 'leading whitespace' => [' native'];
        yield 'trailing whitespace' => ['native '];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidCookieNames(): iterable
    {
        yield 'empty' => [''];
        yield 'contains only numbers' => ['12345'];
        yield 'contains whitespace' => ['FRAMEWORK SESSION'];
        yield 'contains a separator' => ['FRAMEWORK=SESSION'];
    }

    /**
     * @return iterable<string, array{int, int, float, string}>
     */
    public static function invalidRedisEndpoints(): iterable
    {
        yield 'zero port' => [0, 0, 1.0, 'Redis session port must be between 1 and 65535.'];
        yield 'large port' => [65536, 0, 1.0, 'Redis session port must be between 1 and 65535.'];
        yield 'negative database' => [6379, -1, 1.0, 'Redis session database must not be negative.'];
        yield 'zero timeout' => [6379, 0, 0.0, 'Redis session timeout must be a finite positive number.'];
        yield 'negative timeout' => [6379, 0, -1.0, 'Redis session timeout must be a finite positive number.'];
    }

    private function config(
        string $driver = 'native',
        int $lifetime = 7200,
    ): SessionConfig {
        return new SessionConfig(
            driver: $driver,
            lifetime: $lifetime,
            cookie: new SessionCookieConfig('TEST_SESSION', SessionCookieSameSite::Lax),
            native: new SessionNativeConfig('sessions'),
            file: new SessionFileConfig('sessions'),
            database: new SessionDatabaseConfig('sessions'),
            redis: new SessionRedisConfig('127.0.0.1', 6379, 0, null, 'sess:', 2.5),
        );
    }
}
