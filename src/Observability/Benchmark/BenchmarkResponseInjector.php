<?php

declare(strict_types=1);

namespace Lemonade\Framework\Observability\Benchmark;

use Lemonade\Framework\Observability\Benchmark\Config\BenchmarkConfig;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\ResponseInterface;

final class BenchmarkResponseInjector
{
    public function __construct(
        private readonly BenchmarkConfig $config,
    ) {}

    public function inject(ResponseInterface $response, BenchmarkRun $run): ResponseInterface
    {
        $elapsedMs = number_format($run->elapsedMs(), 3, '.', '');
        $memoryDeltaBytes = $run->memoryDeltaBytes();
        $peakBytes = $run->peakMemoryBytes();
        $peakAllocatedBytes = $run->peakAllocatedMemoryBytes();

        $response = $response
            ->withHeader('X-Benchmark-Time-Ms', $elapsedMs)
            ->withHeader('X-Benchmark-Memory-Delta', (string) $memoryDeltaBytes)
            ->withHeader('X-Benchmark-Peak-Memory', (string) $peakBytes)
            ->withHeader('X-Benchmark-Peak-Allocated-Memory', (string) $peakAllocatedBytes);

        if (!$this->config->injectHtmlComment) {
            return $response;
        }

        $contentType = strtolower($response->getHeaderLine('Content-Type'));
        if (!str_contains($contentType, 'text/html')) {
            return $response;
        }

        $body = (string) $response->getBody();
        $body .= PHP_EOL . sprintf(
            '<!-- benchmark: %sms, memory %+s, peak %s used / %s allocated -->',
            $elapsedMs,
            $this->formatBytesSigned($memoryDeltaBytes),
            $this->formatBytes($peakBytes),
            $this->formatBytes($peakAllocatedBytes),
        );

        return $response
            ->withBody(Stream::create($body))
            ->withHeader('Content-Length', (string) strlen($body));
    }

    private function formatBytesSigned(int $bytes): string
    {
        if ($bytes === 0) {
            return '0B';
        }

        $sign = $bytes > 0 ? '+' : '-';

        return $sign . $this->formatBytes(abs($bytes));
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . 'B';
        }

        $kilobytes = $bytes / 1024;
        if ($kilobytes < 1024) {
            return number_format($kilobytes, 2, '.', '') . 'KB';
        }

        return number_format($kilobytes / 1024, 2, '.', '') . 'MB';
    }
}
