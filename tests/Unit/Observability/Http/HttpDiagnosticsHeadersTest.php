<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Observability\Http;

use Lemonade\Framework\Observability\Http\Config\HttpDiagnosticsConfig;
use Lemonade\Framework\Observability\Http\HttpDiagnosticsHeaders;
use Lemonade\Framework\Observability\Http\HttpMetrics;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

final class HttpDiagnosticsHeadersTest extends TestCase
{
    public function testDisabledPresenterReturnsTheExactOriginalResponse(): void
    {
        $response = new Response(200, ['Cache-Control' => 'public, max-age=60'], 'body');
        $presenter = new HttpDiagnosticsHeaders(new HttpDiagnosticsConfig());

        self::assertSame($response, $presenter->apply($response, new HttpMetrics(1.0, 10, 0, 0.0)));
        self::assertSame(['Cache-Control' => ['public, max-age=60']], $response->getHeaders());
    }

    #[DataProvider('responseStatuses')]
    public function testEnabledPresenterOnlyChangesFourDiagnosticHeaders(int $status): void
    {
        $stream = $this->createMock(StreamInterface::class);
        foreach (['__toString', 'read', 'rewind', 'getContents', 'getSize', 'tell', 'seek', 'write'] as $method) {
            $stream->expects(self::never())->method($method);
        }
        $response = new Response(
            $status,
            [
                'Content-Type' => 'application/octet-stream',
                'Content-Encoding' => 'gzip',
                'Content-Length' => '123',
                'ETag' => 'existing-validator',
                'Cache-Control' => 'private, max-age=0',
                'X-Lemonade-Time-Ms' => 'old',
            ],
            $stream,
        );
        $presenter = new HttpDiagnosticsHeaders(new HttpDiagnosticsConfig(true));
        $result = $presenter->apply($response, new HttpMetrics(12.3456, 4096, 7, 3.21));

        self::assertSame($stream, $result->getBody());
        self::assertSame($status, $result->getStatusCode());
        self::assertSame('12.346', $result->getHeaderLine('X-Lemonade-Time-Ms'));
        self::assertSame('4096', $result->getHeaderLine('X-Lemonade-PHP-Peak-Bytes'));
        self::assertSame('7', $result->getHeaderLine('X-Lemonade-SQL-Count'));
        self::assertSame('3.210', $result->getHeaderLine('X-Lemonade-SQL-Time-Ms'));
        self::assertCount(count($response->getHeaders()) + 3, $result->getHeaders());
        foreach (['Content-Type', 'Content-Encoding', 'Content-Length', 'ETag', 'Cache-Control'] as $header) {
            self::assertSame($response->getHeader($header), $result->getHeader($header));
        }
        self::assertSame(['old'], $response->getHeader('X-Lemonade-Time-Ms'));
    }

    /**
     * @return iterable<array{int}>
     */
    public static function responseStatuses(): iterable
    {
        foreach ([200, 204, 206, 302, 304, 400, 404, 500] as $status) {
            yield [$status];
        }
    }
}
