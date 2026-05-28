<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Debug;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Debug\DebugServiceProvider;
use Lemonade\Framework\Debug\Dump\Contract\DumperInterface;
use Lemonade\Framework\Debug\Dump\Contract\DumpOutputInterface;
use Lemonade\Framework\Debug\Dump\Contract\ValueInspectorInterface;
use Lemonade\Framework\Debug\Dump\Dumper;
use Lemonade\Framework\Debug\Dump\DumpOptions;
use Lemonade\Framework\Debug\Dump\Inspector\NativeValueInspector;
use Lemonade\Framework\Debug\Dump\Output\EchoOutput;
use Lemonade\Framework\Debug\Dump\Renderer\CliDumpRenderer;
use Lemonade\Framework\Debug\Dump\Renderer\DumpRendererResolver;
use Lemonade\Framework\Debug\Dump\Renderer\HtmlDumpRenderer;
use PHPUnit\Framework\TestCase;

final class DebugServiceProviderTest extends TestCase
{
    public function testRegisterBindsDumperServices(): void
    {
        $container = new Container();

        (new DebugServiceProvider())->register($container);

        self::assertTrue($container->isBound(DumpOptions::class));
        self::assertTrue($container->isBound(ValueInspectorInterface::class));
        self::assertTrue($container->isBound(DumpOutputInterface::class));
        self::assertTrue($container->isBound(DumpRendererResolver::class));
        self::assertTrue($container->isBound(DumperInterface::class));
        self::assertTrue($container->isBound('dumper'));

        self::assertInstanceOf(NativeValueInspector::class, $container->get(ValueInspectorInterface::class));
        self::assertInstanceOf(EchoOutput::class, $container->get(DumpOutputInterface::class));
        self::assertInstanceOf(CliDumpRenderer::class, $container->get(CliDumpRenderer::class));
        self::assertInstanceOf(HtmlDumpRenderer::class, $container->get(HtmlDumpRenderer::class));
        $dumper = $container->get(DumperInterface::class);

        self::assertInstanceOf(Dumper::class, $dumper);
        self::assertSame($dumper, $container->get(Dumper::class));
        self::assertSame($dumper, $container->get('dumper'));
    }

    public function testDumperIsSingleton(): void
    {
        $container = new Container();

        (new DebugServiceProvider())->register($container);

        self::assertSame(
            $container->get(DumperInterface::class),
            $container->get(DumperInterface::class),
        );
    }
}
