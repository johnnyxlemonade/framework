<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Discovery;

use Lemonade\Framework\Discovery\Sitemap\SitemapUrl;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SitemapUrlTest extends TestCase
{
    public function testValidPriorityBoundaries(): void
    {
        self::assertSame(0.0, SitemapUrl::create('/a', priority: 0.0)->priority());
        self::assertSame(1.0, SitemapUrl::create('/a', priority: 1.0)->priority());
    }

    public function testInvalidValuesThrow(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SitemapUrl::create('', priority: 2.0);
    }

    public function testInvalidChangefreqThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SitemapUrl::create('/x', changefreq: 'wrong');
    }
}
