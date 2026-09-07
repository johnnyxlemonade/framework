<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Core;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Core\CoreServiceProvider;
use Lemonade\Framework\Core\Framework;
use Lemonade\Framework\Http\Psr\ServerRequestFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use ReflectionProperty;

final class Psr17FactoryWiringTest extends TestCase
{
    public function testEarlyNyholmFactoryIsSharedByServerRequestAndPsr17Interfaces(): void
    {
        $framework = $this->framework();
        $container = $framework->container();

        self::assertTrue($container->isBound(Psr17Factory::class));
        $factory = $container->get(Psr17Factory::class);

        $serverRequestFactory = $container->get(ServerRequestFactory::class);
        $property = new ReflectionProperty(ServerRequestFactory::class, 'psr17Factory');

        self::assertSame($factory, $property->getValue($serverRequestFactory));

        $framework->register(new CoreServiceProvider());

        foreach ([
            ResponseFactoryInterface::class,
            RequestFactoryInterface::class,
            ServerRequestFactoryInterface::class,
            StreamFactoryInterface::class,
            UploadedFileFactoryInterface::class,
            UriFactoryInterface::class,
        ] as $service) {
            self::assertSame($factory, $container->get($service));
        }
    }

    private function framework(): Framework
    {
        $context = new ApplicationContext(
            Environment::Testing,
            new Path(__DIR__),
            DebugMode::disabled(),
        );

        return new Framework(new Container(), $context);
    }
}
