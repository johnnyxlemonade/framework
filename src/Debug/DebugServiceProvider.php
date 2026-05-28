<?php

declare(strict_types=1);

namespace Lemonade\Framework\Debug;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Debug\Dump\Context\DumpContextFactory;
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

final class DebugServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        /*
         * Debug dumper.
         */
        $container->singleton(DumpOptions::class, static fn(): DumpOptions => DumpOptions::defaults());

        $container->singleton(DumpContextFactory::class, DumpContextFactory::class);

        $container->singleton(ValueInspectorInterface::class, NativeValueInspector::class);
        $container->singleton(NativeValueInspector::class, NativeValueInspector::class);

        $container->singleton(DumpOutputInterface::class, EchoOutput::class);
        $container->singleton(EchoOutput::class, EchoOutput::class);

        $container->singleton(CliDumpRenderer::class, CliDumpRenderer::class);
        $container->singleton(HtmlDumpRenderer::class, HtmlDumpRenderer::class);

        $container->singleton(DumpRendererResolver::class, static function (ContainerInterface $container): DumpRendererResolver {
            return new DumpRendererResolver([
                $container->get(CliDumpRenderer::class),
                $container->get(HtmlDumpRenderer::class),
            ]);
        });

        $container->singleton(Dumper::class, Dumper::class);

        $container->singleton(
            DumperInterface::class,
            static fn(ContainerInterface $container): DumperInterface => $container->get(Dumper::class),
        );

        $container->singleton(
            'dumper',
            static fn(ContainerInterface $container): DumperInterface => $container->get(DumperInterface::class),
        );
    }
}
