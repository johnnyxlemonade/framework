<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Session;

use Lemonade\Framework\Http\Request\ResolvedRequestContext;
use Lemonade\Framework\Session\Cookie\SessionCookieSameSite;
use Lemonade\Framework\Session\Cookie\SessionCookieSecure;
use Lemonade\Framework\Session\Storage\SessionCookie;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SessionCookieTest extends TestCase
{
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
            requestContext: $this->context('http'),
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
            requestContext: $this->context('http'),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Cannot send session cookie "TEST_SESSION" with SameSite=None over an insecure request; Secure is required.',
        );

        $cookie->send(str_repeat('a', 64));
    }

    public function testAutoPolicyFailsWithoutARequestContext(): void
    {
        $cookie = new SessionCookie(
            'TEST_SESSION',
            60,
            SessionCookieSameSite::Lax,
            static fn(string $name, string $value, array $options): bool => true,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('without a request context');

        $cookie->send(str_repeat('a', 64));
    }

    public function testSameSiteNoneIsAllowedOnAVerifiedSecureRequest(): void
    {
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
            requestContext: $this->context('https'),
        );

        $cookie->send(str_repeat('a', 64));

        self::assertIsArray($emission);
        self::assertTrue($emission['options']['secure']);
        self::assertSame(SessionCookieSameSite::None->value, $emission['options']['samesite']);
    }

    public function testAutoPolicyUsesTheResolvedInsecureScheme(): void
    {
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
            requestContext: $this->context('http'),
        );

        $cookie->send(str_repeat('b', 64));

        self::assertIsArray($emission);
        self::assertFalse($emission['options']['secure']);
    }

    public function testAutoPolicySetsSecureBehindAVerifiedTlsTerminatingProxy(): void
    {
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
            requestContext: $this->context('https', true),
        );

        $cookie->send(str_repeat('c', 64));

        self::assertIsArray($emission);
        self::assertTrue($emission['options']['secure']);
    }

    public function testAlwaysPolicySetsSecureOnAnInsecureRequest(): void
    {
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
            secure: SessionCookieSecure::Always,
            requestContext: $this->context('http'),
        );

        $cookie->send(str_repeat('c', 64));

        self::assertIsArray($emission);
        self::assertTrue($emission['options']['secure']);
    }

    public function testSameSiteNoneIsAllowedWhenAlwaysPolicyProducesSecure(): void
    {
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
            secure: SessionCookieSecure::Always,
            requestContext: $this->context('http'),
        );

        $cookie->send(str_repeat('d', 64));

        self::assertIsArray($emission);
        self::assertTrue($emission['options']['secure']);
        self::assertSame(SessionCookieSameSite::None->value, $emission['options']['samesite']);
    }

    /**
     * @param 'http'|'https' $scheme
     */
    private function context(string $scheme, bool $viaTrustedProxy = false): ResolvedRequestContext
    {
        return new ResolvedRequestContext(
            peerIp: '203.0.113.10',
            clientIp: '203.0.113.10',
            viaTrustedProxy: $viaTrustedProxy,
            scheme: $scheme,
            host: 'example.test',
            port: null,
            prefix: '',
        );
    }
}
