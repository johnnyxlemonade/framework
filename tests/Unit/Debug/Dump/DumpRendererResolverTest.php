<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Debug\Dump;

use Lemonade\Framework\Debug\Dump\Context\DumpContext;
use Lemonade\Framework\Debug\Dump\Context\DumpSourceLocation;
use Lemonade\Framework\Debug\Dump\Contract\DumpRendererInterface;
use Lemonade\Framework\Debug\Dump\Model\Dump;
use Lemonade\Framework\Debug\Dump\Renderer\DumpRendererResolver;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DumpRendererResolverTest extends TestCase
{
    public function testResolveReturnsFirstRendererThatSupportsContext(): void
    {
        $unsupported = new TestDumpRenderer(false, 'unsupported');
        $supported = new TestDumpRenderer(true, 'supported');

        $resolver = new DumpRendererResolver([$unsupported, $supported]);

        self::assertSame($supported, $resolver->resolve($this->context()));
    }

    public function testResolveThrowsWhenNoRendererSupportsContext(): void
    {
        $resolver = new DumpRendererResolver([
            new TestDumpRenderer(false, 'unsupported'),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No dump renderer is available for current context.');

        $resolver->resolve($this->context());
    }

    private function context(): DumpContext
    {
        return new DumpContext(
            sourceLocation: new DumpSourceLocation('/tmp/test.php', 10),
            cli: false,
            sapi: 'fpm-fcgi',
        );
    }
}

final class TestDumpRenderer implements DumpRendererInterface
{
    public function __construct(
        private readonly bool $supported,
        private readonly string $output,
    ) {}

    public function supports(DumpContext $context): bool
    {
        unset($context);

        return $this->supported;
    }

    public function render(Dump $dump): string
    {
        unset($dump);

        return $this->output;
    }
}
