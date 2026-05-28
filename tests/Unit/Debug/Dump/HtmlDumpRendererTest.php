<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Debug\Dump;

use Lemonade\Framework\Debug\Dump\Context\DumpContext;
use Lemonade\Framework\Debug\Dump\Context\DumpSourceLocation;
use Lemonade\Framework\Debug\Dump\DumpOptions;
use Lemonade\Framework\Debug\Dump\Model\Dump;
use Lemonade\Framework\Debug\Dump\Model\DumpItem;
use Lemonade\Framework\Debug\Dump\Model\DumpNode;
use Lemonade\Framework\Debug\Dump\Model\DumpType;
use Lemonade\Framework\Debug\Dump\Renderer\HtmlDumpRenderer;
use PHPUnit\Framework\TestCase;

final class HtmlDumpRendererTest extends TestCase
{
    public function testSupportsOnlyNonCliContext(): void
    {
        $renderer = new HtmlDumpRenderer(DumpOptions::defaults());

        self::assertTrue($renderer->supports($this->context(cli: false)));
        self::assertFalse($renderer->supports($this->context(cli: true)));
    }

    public function testRenderEscapesValuesAndIncludesDefaultStylesOnce(): void
    {
        $renderer = new HtmlDumpRenderer(DumpOptions::defaults());

        $dump = new Dump(
            context: $this->context(cli: false),
            items: [
                new DumpItem(
                    index: 1,
                    value: new DumpNode(
                        type: DumpType::STRING,
                        label: '<label>',
                        value: '<script>alert(1)</script>',
                    ),
                ),
            ],
        );

        $first = $renderer->render($dump);
        $second = $renderer->render($dump);

        self::assertStringContainsString('<style>', $first);
        self::assertStringNotContainsString('<style>', $second);
        self::assertStringContainsString('&lt;label&gt;', $first);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $first);
        self::assertStringContainsString('Debug #1 of 1', $first);
    }

    public function testRenderCanDisableInlineStyles(): void
    {
        $renderer = new HtmlDumpRenderer(new DumpOptions(includeHtmlStyles: false));

        $output = $renderer->render(new Dump(
            context: $this->context(cli: false),
            items: [
                new DumpItem(
                    index: 1,
                    value: new DumpNode(DumpType::INT, 'int', '1'),
                ),
            ],
        ));

        self::assertStringNotContainsString('<style>', $output);
        self::assertStringContainsString('int', $output);
    }

    private function context(bool $cli): DumpContext
    {
        return new DumpContext(
            sourceLocation: new DumpSourceLocation('/tmp/test.php', 10),
            cli: $cli,
            sapi: $cli ? 'cli' : 'fpm-fcgi',
        );
    }
}
