<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Session;

use Lemonade\Framework\Session\Cookie\SessionCookieSameSite;
use Lemonade\Framework\Session\Storage\SessionCookie;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SessionCookieTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER = [];
    }

    protected function tearDown(): void
    {
        $_SERVER = [];

        parent::tearDown();
    }

    public function testCookieUsesTheConfiguredEnumAndSharedSecurityAttributes(): void
    {
        $emission = null;
        $cookie = new SessionCookie(
            'TEST_SESSION',
            60,
            SessionCookieSameSite::Strict,
            static function (string $name, string $value, array $options) use (&$emission): bool {
                $emission = [
                    'name' => $name,
                    'value' => $value,
                    'options' => $options,
                ];

                return true;
            },
        );

        $cookie->send(str_repeat('a', 64));

        self::assertIsArray($emission);
        self::assertSame('/', $emission['options']['path']);
        self::assertFalse($emission['options']['secure']);
        self::assertTrue($emission['options']['httponly']);
        self::assertSame(SessionCookieSameSite::Strict->value, $emission['options']['samesite']);
    }

    public function testSameSiteNoneFailsExplicitlyOnAnInsecureRequest(): void
    {
        $cookie = new SessionCookie(
            'TEST_SESSION',
            60,
            SessionCookieSameSite::None,
            static fn(string $name, string $value, array $options): bool => true,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Cannot send session cookie "TEST_SESSION" with SameSite=None over an insecure request; Secure is required.',
        );

        $cookie->send(str_repeat('a', 64));
    }

    public function testSameSiteNoneIsAllowedOnADirectSecureRequest(): void
    {
        $_SERVER['HTTPS'] = 'on';
        $emission = null;
        $cookie = new SessionCookie(
            'TEST_SESSION',
            60,
            SessionCookieSameSite::None,
            static function (string $name, string $value, array $options) use (&$emission): bool {
                $emission = [
                    'name' => $name,
                    'value' => $value,
                    'options' => $options,
                ];

                return true;
            },
        );

        $cookie->send(str_repeat('a', 64));

        self::assertIsArray($emission);
        self::assertTrue($emission['options']['secure']);
        self::assertSame(SessionCookieSameSite::None->value, $emission['options']['samesite']);
    }

    public function testDirectHttpsDetectionTreatsOffCaseInsensitively(): void
    {
        $_SERVER['HTTPS'] = 'OFF';
        $emission = null;
        $cookie = new SessionCookie(
            'TEST_SESSION',
            60,
            SessionCookieSameSite::Lax,
            static function (string $name, string $value, array $options) use (&$emission): bool {
                $emission = [
                    'name' => $name,
                    'value' => $value,
                    'options' => $options,
                ];

                return true;
            },
        );

        $cookie->send(str_repeat('b', 64));

        self::assertIsArray($emission);
        self::assertFalse($emission['options']['secure']);
    }

    public function testDirectHttpsDetectionAcceptsTheCanonicalServerPortValue(): void
    {
        $_SERVER['SERVER_PORT'] = '443';
        $emission = null;
        $cookie = new SessionCookie(
            'TEST_SESSION',
            60,
            SessionCookieSameSite::Lax,
            static function (string $name, string $value, array $options) use (&$emission): bool {
                $emission = [
                    'name' => $name,
                    'value' => $value,
                    'options' => $options,
                ];

                return true;
            },
        );

        $cookie->send(str_repeat('c', 64));

        self::assertIsArray($emission);
        self::assertTrue($emission['options']['secure']);
    }
}
