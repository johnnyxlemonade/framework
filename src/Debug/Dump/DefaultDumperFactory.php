<?php

declare(strict_types=1);

namespace Lemonade\Framework\Debug\Dump;

use Lemonade\Framework\Debug\Dump\Context\DumpContextFactory;
use Lemonade\Framework\Debug\Dump\Contract\DumperInterface;
use Lemonade\Framework\Debug\Dump\Inspector\NativeValueInspector;
use Lemonade\Framework\Debug\Dump\Output\EchoOutput;
use Lemonade\Framework\Debug\Dump\Renderer\CliDumpRenderer;
use Lemonade\Framework\Debug\Dump\Renderer\DumpRendererResolver;
use Lemonade\Framework\Debug\Dump\Renderer\HtmlDumpRenderer;

final class DefaultDumperFactory
{
    public static function create(?DumpOptions $options = null): DumperInterface
    {
        $options ??= DumpOptions::defaults();

        return new Dumper(
            contextFactory: new DumpContextFactory(),
            inspector: new NativeValueInspector(),
            rendererResolver: new DumpRendererResolver([
                new CliDumpRenderer(),
                new HtmlDumpRenderer($options),
            ]),
            output: new EchoOutput(),
            options: $options,
        );
    }
}
