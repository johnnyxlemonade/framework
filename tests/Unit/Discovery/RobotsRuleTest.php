<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Discovery;

use Lemonade\Framework\Discovery\Robots\RobotsRule;
use PHPUnit\Framework\TestCase;

final class RobotsRuleTest extends TestCase
{
    public function testImmutableConstruction(): void
    {
        $rule = new RobotsRule('*', ['/'], ['/admin']);

        self::assertSame('*', $rule->userAgent());
        self::assertSame(['/'], $rule->allow());
        self::assertSame(['/admin'], $rule->disallow());
    }
}
