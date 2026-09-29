<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Http\Response;

use JsonException;
use Lemonade\Framework\Core\Http\ResponseBuilder;
use Lemonade\Framework\Http\Response\Responses;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class ResponsesTest extends TestCase
{
    /** @var list<string> */
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

    public function testHtmlAndTextCreateTypedResponses(): void
    {
        $responses = $this->responses();

        $html = $responses->html('<h1>Hi</h1>', 202);
        self::assertSame(202, $html->getStatusCode());
        self::assertSame('text/html; charset=UTF-8', $html->getHeaderLine('Content-Type'));
        self::assertSame('<h1>Hi</h1>', (string) $html->getBody());

        $text = $responses->text('hello', 201);
        self::assertSame(201, $text->getStatusCode());
        self::assertSame('text/plain; charset=UTF-8', $text->getHeaderLine('Content-Type'));
        self::assertSame('hello', (string) $text->getBody());
    }

    public function testJsonUsesExistingEncodingPolicy(): void
    {
        $response = $this->responses()->json([
            'url' => 'https://example.com/a/b',
            'text' => 'Příliš žluťoučký kůň',
        ]);

        self::assertSame('application/json; charset=UTF-8', $response->getHeaderLine('Content-Type'));
        self::assertSame(
            '{"url":"https://example.com/a/b","text":"Příliš žluťoučký kůň"}',
            (string) $response->getBody(),
        );
    }

    public function testJsonThrowsWhenPayloadCannotBeEncoded(): void
    {
        $this->expectException(JsonException::class);

        $this->responses()->json(['invalid' => INF]);
    }

    public function testRedirectSetsStatusAndLocationHeader(): void
    {
        $response = $this->responses()->redirect('/target', 301);

        self::assertSame(301, $response->getStatusCode());
        self::assertSame('/target', $response->getHeaderLine('Location'));
    }

    public function testDownloadSetsHeadersAndStreamsFileBody(): void
    {
        $file = $this->createTempFile('download body');
        $response = $this->responses()->download($file, 'report.txt', 'text/plain');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/plain', $response->getHeaderLine('Content-Type'));
        self::assertSame('attachment; filename="report.txt"', $response->getHeaderLine('Content-Disposition'));
        self::assertSame((string) filesize($file), $response->getHeaderLine('Content-Length'));
        self::assertSame('download body', (string) $response->getBody());
    }

    public function testStreamSetsHeadersAndReadableCallbackBody(): void
    {
        $response = $this->responses()->stream(
            producer: static function (): void {
                echo 'streamed';
            },
            status: 206,
            headers: ['X-Custom' => 'yes'],
        );

        self::assertSame(206, $response->getStatusCode());
        self::assertSame('text/plain; charset=UTF-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('yes', $response->getHeaderLine('X-Custom'));
        self::assertSame('streamed', $response->getBody()->getContents());
    }

    private function responses(): Responses
    {
        $factory = new Psr17Factory();

        return new Responses(new ResponseBuilder($factory, $factory));
    }

    private function createTempFile(string $content): string
    {
        $path = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'lemonade-responses-' . uniqid('', true) . '.tmp';
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;

        return $path;
    }
}
