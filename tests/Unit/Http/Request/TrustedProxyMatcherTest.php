<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Http\Request;

use InvalidArgumentException;
use Lemonade\Framework\Http\Request\TrustedProxyMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TrustedProxyMatcherTest extends TestCase
{
    public function testMatchesExactAndCidrIpv4AndIpv6Entries(): void
    {
        $matcher = new TrustedProxyMatcher([
            '192.0.2.10',
            '10.20.0.0/16',
            '2001:db8::10',
            'fd00:1234::/32',
        ]);

        self::assertTrue($matcher->isTrusted('192.0.2.10'));
        self::assertFalse($matcher->isTrusted('192.0.2.11'));
        self::assertTrue($matcher->isTrusted('10.20.255.255'));
        self::assertFalse($matcher->isTrusted('10.21.0.1'));
        self::assertTrue($matcher->isTrusted('2001:db8::10'));
        self::assertFalse($matcher->isTrusted('2001:db8::11'));
        self::assertTrue($matcher->isTrusted('fd00:1234:ffff::1'));
        self::assertFalse($matcher->isTrusted('fd00:1235::1'));
        self::assertFalse($matcher->isTrusted('not-an-ip'));
    }

    public function testZeroPrefixExplicitlyMatchesOnlyItsAddressFamily(): void
    {
        $ipv4 = new TrustedProxyMatcher(['0.0.0.0/0']);
        $ipv6 = new TrustedProxyMatcher(['::/0']);

        self::assertTrue($ipv4->isTrusted('203.0.113.8'));
        self::assertFalse($ipv4->isTrusted('2001:db8::1'));
        self::assertTrue($ipv6->isTrusted('2001:db8::1'));
        self::assertFalse($ipv6->isTrusted('203.0.113.8'));
    }

    #[DataProvider('invalidNetworks')]
    public function testRejectsMalformedNetworks(string $network): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TrustedProxyMatcher([$network]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNetworks(): iterable
    {
        yield 'empty' => [''];
        yield 'hostname' => ['proxy.example'];
        yield 'invalid address' => ['999.0.0.1'];
        yield 'negative prefix' => ['10.0.0.0/-1'];
        yield 'oversized IPv4 prefix' => ['10.0.0.0/33'];
        yield 'oversized IPv6 prefix' => ['2001:db8::/129'];
        yield 'multiple slashes' => ['10.0.0.0/8/1'];
        yield 'zone identifier' => ['fe80::1%eth0'];
    }
}
