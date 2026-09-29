<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Filesystem;

use InvalidArgumentException;
use Lemonade\Framework\Filesystem\DirectoryPathGenerator;
use PHPUnit\Framework\TestCase;

final class DirectoryPathGeneratorTest extends TestCase
{
    public function testShardIsDeterministicAndRelative(): void
    {
        $generator = new DirectoryPathGenerator();
        self::assertSame('1c/49/a0/83/', $generator->shard('asset-1', 4));
        self::assertSame($generator->shard('asset-1'), $generator->shard('asset-1'));
        self::assertStringStartsNotWith('/', $generator->shard('asset-1'));
    }

    public function testShardAcceptsArbitraryKeysAndExplicitDepth(): void
    {
        $generator = new DirectoryPathGenerator();
        self::assertSame(3, substr_count(rtrim($generator->shard('../arbitrary key', 3), '/'), '/') + 1);
        self::assertNotSame($generator->shard('first'), $generator->shard('second'));
        self::assertSame(8, substr_count(rtrim($generator->shard('default'), '/'), '/') + 1);
        self::assertSame($generator->shard('42'), $generator->shard(42));
    }

    public function testShardRejectsDepthOutsideSha256Segments(): void
    {
        $generator = new DirectoryPathGenerator();
        $this->expectException(InvalidArgumentException::class);
        $generator->shard('asset', 33);
    }
}
