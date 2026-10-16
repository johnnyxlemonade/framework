<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Session\Config;

use InvalidArgumentException;
use Lemonade\Framework\Session\Config\SessionConfigDefinition;
use Lemonade\Framework\Session\Config\SessionConfigResolver;
use Lemonade\Framework\Session\Cookie\SessionCookieSameSite;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SessionConfigResolverTest extends TestCase
{
    public function testItResolvesCanonicalFrameworkDefaultsWithoutDefinitions(): void
    {
        $config = (new SessionConfigResolver())->resolve();

        self::assertSame('native', $config->driver());
        self::assertSame('LEMONADE_SESSION', $config->cookie()->name());
        self::assertSame(SessionCookieSameSite::Lax, $config->cookie()->sameSite());
        self::assertSame(7200, $config->lifetime());
        self::assertSame('sessions', $config->native()->path());
        self::assertSame('sessions', $config->file()->path());
        self::assertSame('sessions', $config->database()->table());
        self::assertSame('127.0.0.1', $config->redis()->host());
        self::assertSame(6379, $config->redis()->port());
        self::assertSame(0, $config->redis()->database());
        self::assertNull($config->redis()->password());
        self::assertSame('sess:', $config->redis()->prefix());
        self::assertSame(2.5, $config->redis()->timeout());
    }

    public function testItPreservesAbsentFieldsFromEarlierContributions(): void
    {
        $config = (new SessionConfigResolver())->resolve(
            SessionConfigDefinition::create()
                ->cookieName('FIRST_SESSION')
                ->redisHost('redis.internal')
                ->redisPassword('secret'),
            SessionConfigDefinition::create()->lifetime(3600),
        );

        self::assertSame('FIRST_SESSION', $config->cookie()->name());
        self::assertSame(3600, $config->lifetime());
        self::assertSame('redis.internal', $config->redis()->host());
        self::assertSame('secret', $config->redis()->password());
    }

    public function testItAllowsAnExplicitNullRedisPasswordToClearThePreviousValue(): void
    {
        $config = (new SessionConfigResolver())->resolve(
            SessionConfigDefinition::create()->redisPassword('secret'),
            SessionConfigDefinition::create()->redisPassword(null),
        );

        self::assertNull($config->redis()->password());
    }

    #[DataProvider('sameSiteValues')]
    public function testItResolvesConfiguredSameSiteValues(
        string $configured,
        SessionCookieSameSite $expected,
    ): void {
        $definition = SessionConfigDefinition::fromArrayData([
            'cookie' => [
                'name' => 'CONFIGURED_SESSION',
                'same_site' => $configured,
            ],
        ]);

        $config = (new SessionConfigResolver())->resolve($definition);

        self::assertSame('CONFIGURED_SESSION', $config->cookie()->name());
        self::assertSame($expected, $config->cookie()->sameSite());
    }

    public function testItRejectsAnUnsupportedSameSiteValue(): void
    {
        $definition = SessionConfigDefinition::fromArrayData([
            'cookie' => [
                'same_site' => 'cross-site',
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Invalid session cookie same_site value "cross-site". Expected one of: lax, strict, none.',
        );

        (new SessionConfigResolver())->resolve($definition);
    }

    public function testTypedConfigurationRejectsNonPositiveResolvedLifetime(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Session lifetime must be positive.');

        (new SessionConfigResolver())->resolve(
            SessionConfigDefinition::create()->lifetime(0),
        );
    }

    public function testTypedConfigurationRejectsAnExplicitlyEmptyDriver(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Session driver must be a non-empty canonical lowercase identifier.');

        (new SessionConfigResolver())->resolve(
            SessionConfigDefinition::fromArrayData(['driver' => '']),
        );
    }

    public function testTypedConfigurationRejectsAnExplicitlyEmptyCookieName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Session cookie name must contain a letter');

        (new SessionConfigResolver())->resolve(
            SessionConfigDefinition::fromArrayData([
                'cookie' => ['name' => ''],
            ]),
        );
    }

    /**
     * @return iterable<string, array{string, SessionCookieSameSite}>
     */
    public static function sameSiteValues(): iterable
    {
        yield 'lax' => ['lax', SessionCookieSameSite::Lax];
        yield 'strict' => ['strict', SessionCookieSameSite::Strict];
        yield 'none' => ['none', SessionCookieSameSite::None];
    }
}
