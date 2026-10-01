<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Core\Logging;

use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Core\Logging\LogFilePathResolver;
use PHPUnit\Framework\TestCase;

final class LogFilePathResolverTest extends TestCase
{
    public function testBuiltInChannelsResolveToCanonicalStorageFiles(): void
    {
        $context = new ApplicationContext(
            Environment::Testing,
            new Path('/var/www/framework', '/var/www/framework/public'),
            DebugMode::disabled(),
        );

        $resolver = new LogFilePathResolver($context);

        self::assertSame('/var/www/framework/storage/writable/logs/app.log', $resolver->resolve('app'));
        self::assertSame('/var/www/framework/storage/writable/logs/error.log', $resolver->resolve('error'));
        self::assertSame('/var/www/framework/storage/writable/logs/request.log', $resolver->resolve('request'));
        self::assertSame('/var/www/framework/storage/writable/logs/benchmark.log', $resolver->resolve('benchmark'));
    }
}
