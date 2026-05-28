<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Debug\Dump;

use Lemonade\Framework\Debug\Dump\Context\DumpContext;
use Lemonade\Framework\Debug\Dump\Context\DumpSourceLocation;
use Lemonade\Framework\Debug\Dump\Model\Dump;
use Lemonade\Framework\Debug\Dump\Model\DumpItem;
use Lemonade\Framework\Debug\Dump\Model\DumpNode;
use Lemonade\Framework\Debug\Dump\Model\DumpType;
use Lemonade\Framework\Debug\Dump\Renderer\CliDumpRenderer;
use PHPUnit\Framework\TestCase;

final class CliDumpRendererTest extends TestCase
{
    public function testSupportsOnlyCliContext(): void
    {
        $renderer = new CliDumpRenderer();

        self::assertTrue($renderer->supports($this->context(cli: true)));
        self::assertFalse($renderer->supports($this->context(cli: false)));
    }

    public function testRenderReturnsReadableCliOutput(): void
    {
        $renderer = new CliDumpRenderer();

        $output = $renderer->render(new Dump(
            context: $this->context(cli: true),
            items: [
                new DumpItem(
                    index: 1,
                    value: new DumpNode(
                        type: DumpType::ARRAY,
                        label: 'array',
                        value: 'array(1)',
                        children: [
                            new DumpNode(
                                type: DumpType::ENTRY,
                                label: 'name',
                                children: [
                                    new DumpNode(DumpType::STRING, 'string', 'Jan'),
                                ],
                            ),
                        ],
                    ),
                ),
            ],
        ));

        self::assertStringContainsString('--- dump: /tmp/test.php:10 ---', $output);
        self::assertStringContainsString('Debug #1 of 1:', $output);
        self::assertStringContainsString('array <array>: array(1)', $output);
        self::assertStringContainsString('name <entry>', $output);
        self::assertStringContainsString('string <string>: Jan', $output);
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
