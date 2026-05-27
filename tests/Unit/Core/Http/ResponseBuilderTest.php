<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Core\Http;

use Lemonade\Framework\Core\Http\ResponseBuilder;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class ResponseBuilderTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        $this->tempFiles = [];
    }

    public function testTextSetsStatusContentTypeAndBody(): void
    {
        $builder = $this->builder();
        $response = $builder->text('hello', 201);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('text/plain; charset=UTF-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('hello', (string) $response->getBody());
    }

    public function testHtmlSetsStatusContentTypeAndBody(): void
    {
        $builder = $this->builder();
        $response = $builder->html('<h1>Hi</h1>', 202);

        self::assertSame(202, $response->getStatusCode());
        self::assertSame('text/html; charset=UTF-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('<h1>Hi</h1>', (string) $response->getBody());
    }

    public function testJsonSerializesWithoutEscapingUnicodeOrSlashesAndSetsContentType(): void
    {
        $builder = $this->builder();
        $response = $builder->json([
            'url' => 'https://example.com/a/b',
            'text' => 'Příliš žluťoučký kůň',
        ], 200);

        self::assertSame('application/json; charset=UTF-8', $response->getHeaderLine('Content-Type'));
        self::assertSame(
            '{"url":"https://example.com/a/b","text":"Příliš žluťoučký kůň"}',
            (string) $response->getBody(),
        );
    }

    public function testRedirectSetsStatusAndLocationHeader(): void
    {
        $builder = $this->builder();
        $response = $builder->redirect('/target', 301);

        self::assertSame(301, $response->getStatusCode());
        self::assertSame('/target', $response->getHeaderLine('Location'));
    }

    public function testResponseWithEmptyBodyReturnsResponseWithoutBodyWrite(): void
    {
        $builder = $this->builder();
        $response = $builder->response('', 204, 'text/plain; charset=UTF-8');

        self::assertSame(204, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
        self::assertSame('text/plain; charset=UTF-8', $response->getHeaderLine('Content-Type'));
    }

    public function testResponseWithContentSetsBodyAndContentType(): void
    {
        $builder = $this->builder();
        $response = $builder->response('content', 200, 'application/custom');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/custom', $response->getHeaderLine('Content-Type'));
        self::assertSame('content', (string) $response->getBody());
    }

    public function testDownloadSetsHeadersAndBodyFromFile(): void
    {
        $builder = $this->builder();
        $file = $this->createTempFile('download body');

        $response = $builder->download($file, 'report.txt', 'text/plain');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/plain', $response->getHeaderLine('Content-Type'));
        self::assertSame('attachment; filename="report.txt"', $response->getHeaderLine('Content-Disposition'));
        self::assertSame((string) filesize($file), $response->getHeaderLine('Content-Length'));
        self::assertSame('private, no-transform, no-store, must-revalidate', $response->getHeaderLine('Cache-Control'));
        self::assertSame('download body', (string) $response->getBody());
    }

    public function testStreamSetsHeadersAndReadableCallbackBody(): void
    {
        $builder = $this->builder();

        $response = $builder->stream(
            producer: static function (): void {
                echo 'streamed';
            },
            status: 206,
            contentType: 'text/plain; charset=UTF-8',
            headers: ['X-Custom' => 'yes'],
        );

        self::assertSame(206, $response->getStatusCode());
        self::assertSame('text/plain; charset=UTF-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('yes', $response->getHeaderLine('X-Custom'));
        self::assertSame('streamed', $response->getBody()->getContents());
    }

    private function builder(): ResponseBuilder
    {
        $psr17 = new Psr17Factory();

        return new ResponseBuilder($psr17, $psr17);
    }

    private function createTempFile(string $content): string
    {
        $path = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'lemonade-response-' . uniqid('', true) . '.tmp';
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;

        return $path;
    }
}
