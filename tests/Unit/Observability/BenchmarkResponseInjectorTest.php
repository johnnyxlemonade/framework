<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Observability;

use Lemonade\Framework\Observability\Benchmark\BenchmarkResponseInjector;
use Lemonade\Framework\Observability\Benchmark\BenchmarkRun;
use Lemonade\Framework\Observability\Benchmark\Config\BenchmarkConfig;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class BenchmarkResponseInjectorTest extends TestCase
{
    public function testInjectAddsHtmlCommentWhenEnabled(): void
    {
        $injector = new BenchmarkResponseInjector(new BenchmarkConfig(true));
        $run = new BenchmarkRun();

        $response = $injector->inject(
            new Response(200, ['Content-Type' => 'text/html'], '<html></html>'),
            $run,
        );

        self::assertStringContainsString('benchmark:', (string) $response->getBody());
        self::assertTrue($response->hasHeader('X-Benchmark-Time-Ms'));
    }

    public function testInjectSkipsHtmlCommentWhenDisabled(): void
    {
        $injector = new BenchmarkResponseInjector(new BenchmarkConfig(false));
        $run = new BenchmarkRun();

        $response = $injector->inject(
            new Response(200, ['Content-Type' => 'text/html'], '<html></html>'),
            $run,
        );

        self::assertSame('<html></html>', (string) $response->getBody());
    }
}
