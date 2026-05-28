<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Debug\Dump;

use Lemonade\Framework\Debug\Dump\Context\DumpContextFactory;
use Lemonade\Framework\Debug\Dump\Contract\DumpOutputInterface;
use Lemonade\Framework\Debug\Dump\Dumper;
use Lemonade\Framework\Debug\Dump\DumpOptions;
use Lemonade\Framework\Debug\Dump\Inspector\NativeValueInspector;
use Lemonade\Framework\Debug\Dump\Renderer\CliDumpRenderer;
use Lemonade\Framework\Debug\Dump\Renderer\DumpRendererResolver;
use PHPUnit\Framework\TestCase;

final class DumperTest extends TestCase
{
    public function testRenderBuildsDumpOutput(): void
    {
        $dumper = new Dumper(
            contextFactory: new DumpContextFactory(),
            inspector: new NativeValueInspector(),
            rendererResolver: new DumpRendererResolver([new CliDumpRenderer()]),
            output: new CapturingDumpOutput(),
            options: DumpOptions::defaults(),
        );

        $output = $dumper->render(['name' => 'Jan']);

        self::assertStringContainsString('array <array>: array(1)', $output);
        self::assertStringContainsString('name <entry>', $output);
        self::assertStringContainsString('string <string>: Jan', $output);
    }

    public function testDumpWritesRenderedOutput(): void
    {
        $output = new CapturingDumpOutput();

        $dumper = new Dumper(
            contextFactory: new DumpContextFactory(),
            inspector: new NativeValueInspector(),
            rendererResolver: new DumpRendererResolver([new CliDumpRenderer()]),
            output: $output,
            options: DumpOptions::defaults(),
        );

        $dumper->dump('hello');

        self::assertStringContainsString('hello', $output->contents());
    }
}

final class CapturingDumpOutput implements DumpOutputInterface
{
    private string $contents = '';

    public function write(string $contents): void
    {
        $this->contents .= $contents;
    }

    public function contents(): string
    {
        return $this->contents;
    }
}
