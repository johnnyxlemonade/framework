<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Routing;

use Lemonade\Framework\Routing\RoutePathNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RoutePathNormalizerTest extends TestCase
{
    #[DataProvider('pathProvider')]
    public function testNormalizeUsesRouterPathSemantics(string $path, string $expected): void
    {
        self::assertSame($expected, RoutePathNormalizer::normalize($path));
    }

    /** @return iterable<string, array{string, string}> */
    public static function pathProvider(): iterable
    {
        yield 'empty path is root' => ['', '/'];
        yield 'root is preserved' => ['/', '/'];
        yield 'leading slash is added' => ['admin/login', '/admin/login'];
        yield 'trailing slash is removed' => ['/admin/login/', '/admin/login'];
        yield 'surrounding slashes are removed' => ['//admin/login//', '/admin/login'];
    }
}
