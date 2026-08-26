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
    public function testRelativeLogPathResolvesIntoStorageWritableLogs(): void
    {
        $context = new ApplicationContext(
            Environment::Testing,
            new Path('/var/www/framework', '/var/www/framework/public'),
            DebugMode::disabled(),
        );

        $resolver = new LogFilePathResolver($context);

        self::assertSame(
            '/var/www/framework/storage/writable/logs/error.log',
            $resolver->resolve('error.log', 'fallback.log'),
        );
    }

    public function testAbsoluteLogPathIsPreserved(): void
    {
        $context = new ApplicationContext(
            Environment::Testing,
            new Path('C:\\laragon\\www\\framework', 'C:\\laragon\\www\\framework\\public'),
            DebugMode::disabled(),
        );

        $resolver = new LogFilePathResolver($context);

        self::assertSame(
            'C:\\logs\\error.log',
            $resolver->resolve('C:\\logs\\error.log', 'fallback.log'),
        );
    }
}
