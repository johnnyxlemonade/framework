<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Http\Psr;

use Lemonade\Framework\Http\Psr\ResponseEmitter;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

final class ResponseEmitterTest extends TestCase
{
    protected function tearDown(): void
    {
        http_response_code(200);
        if (function_exists('header_remove')) {
            header_remove();
        }
    }

    public function testEmitOutputsBodyAndSetsStatusCode(): void
    {
        $factory = new Psr17Factory();
        $response = $factory->createResponse(204)->withBody($factory->createStream('payload'));
        $emitter = new ResponseEmitter();

        ob_start();
        $emitter->emit($response);
        $output = ob_get_clean();

        self::assertSame('payload', is_string($output) ? $output : '');
        self::assertSame(204, http_response_code());
    }

    public function testEmitHandlesMultipleHeaderValuesAndEmptyBody(): void
    {
        $factory = new Psr17Factory();
        $response = $factory->createResponse(201)
            ->withAddedHeader('X-Test', 'a')
            ->withAddedHeader('X-Test', 'b');
        $emitter = new ResponseEmitter();

        ob_start();
        $emitter->emit($response);
        $output = ob_get_clean();

        self::assertSame('', is_string($output) ? $output : '');
        self::assertSame(201, http_response_code());

        $headers = function_exists('headers_list') ? headers_list() : [];
        if ($headers !== []) {
            self::assertContains('X-Test: a', $headers);
            self::assertContains('X-Test: b', $headers);
        } else {
            self::addToAssertionCount(1);
        }
    }

    public function testEmitDoesNotOutputBodyForHeadRequest(): void
    {
        $factory = new Psr17Factory();
        $response = $factory->createResponse(202)
            ->withHeader('X-Head', 'ok')
            ->withBody($factory->createStream('payload'));
        $request = new ServerRequest('HEAD', '/users');
        $emitter = new ResponseEmitter();

        ob_start();
        $emitter->emit($response, $request);
        $output = ob_get_clean();

        self::assertSame('', is_string($output) ? $output : '');
        self::assertSame(202, http_response_code());

        $headers = function_exists('headers_list') ? headers_list() : [];
        if ($headers !== []) {
            self::assertContains('X-Head: ok', $headers);
        } else {
            self::addToAssertionCount(1);
        }
    }

    public function testEmitReadsReadableStreamInChunksWithoutStringCasting(): void
    {
        $factory = new Psr17Factory();
        $content = str_repeat('streamed-', 30000);
        $body = new InstrumentedStream($factory->createStream($content), 8192);
        $response = $factory->createResponse()->withBody($body);

        ob_start();
        (new ResponseEmitter())->emit($response);
        $output = ob_get_clean();

        self::assertSame($content, is_string($output) ? $output : '');
        self::assertSame(0, $body->stringCastCalls());
        self::assertGreaterThan(1, count($body->readLengths()));

        foreach ($body->readLengths() as $readLength) {
            self::assertLessThan(strlen($content), $readLength);
        }
    }

    public function testEmitStreamsDownloadFileBodyInChunksAndPreservesDownloadHeaders(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'lemonade-download-');
        self::assertNotFalse($path);
        file_put_contents($path, str_repeat('file-chunk-', 20000));

        try {
            $factory = new Psr17Factory();
            $builder = new \Lemonade\Framework\Core\Http\ResponseBuilder($factory, $factory);
            $download = $builder->download($path, 'report.txt', 'text/plain');
            $body = new InstrumentedStream($download->getBody(), 8192);
            $response = $download->withBody($body);

            ob_start();
            (new ResponseEmitter())->emit($response);
            $output = ob_get_clean();

            self::assertSame((string) file_get_contents($path), is_string($output) ? $output : '');
            self::assertSame('attachment; filename="report.txt"', $response->getHeaderLine('Content-Disposition'));
            self::assertSame('text/plain', $response->getHeaderLine('Content-Type'));
            self::assertSame((string) filesize($path), $response->getHeaderLine('Content-Length'));
            self::assertSame('private, no-transform, no-store, must-revalidate', $response->getHeaderLine('Cache-Control'));
            self::assertGreaterThan(1, count($body->readLengths()));
            self::assertSame(0, $body->stringCastCalls());

            foreach ($body->readLengths() as $readLength) {
                self::assertLessThan((int) filesize($path), $readLength);
            }
        } finally {
            @unlink($path);
        }
    }

    public function testIterableProducerStartsLazilyAndStreamsMultipleChunks(): void
    {
        $events = [];
        $factory = new Psr17Factory();
        $response = (new \Lemonade\Framework\Core\Http\ResponseBuilder($factory, $factory))->stream(
            static function () use (&$events): iterable {
                $events[] = 'started';
                yield 'first';
                $events[] = 'advanced';
                yield 'second';
            },
        );
        $body = $response->getBody();

        self::assertSame([], $events);
        self::assertSame('f', $body->read(1));
        self::assertSame(['started'], $events);
        self::assertSame('irstsecond', $body->getContents());
        self::assertSame(['started', 'advanced'], $events);
        self::assertTrue($body->eof());
    }

    public function testEmitHandlesEmptyIterableProducer(): void
    {
        $factory = new Psr17Factory();
        $response = (new \Lemonade\Framework\Core\Http\ResponseBuilder($factory, $factory))->stream(
            static function (): iterable {
                return [];
            },
        );

        ob_start();
        (new ResponseEmitter())->emit($response);
        $output = ob_get_clean();

        self::assertSame('', is_string($output) ? $output : '');
        self::assertTrue($response->getBody()->eof());
    }
}

final class InstrumentedStream implements StreamInterface
{
    /** @var list<int> */
    private array $readLengths = [];
    private int $stringCastCalls = 0;

    public function __construct(
        private readonly StreamInterface $inner,
        private readonly int $maximumReadSize,
    ) {
    }

    public function __toString(): string
    {
        $this->stringCastCalls++;

        throw new RuntimeException('Response emitter must not cast a stream to string.');
    }

    public function close(): void { $this->inner->close(); }
    public function detach(): mixed { return $this->inner->detach(); }
    public function getSize(): ?int { return $this->inner->getSize(); }
    public function tell(): int { return $this->inner->tell(); }
    public function eof(): bool { return $this->inner->eof(); }
    public function isSeekable(): bool { return $this->inner->isSeekable(); }
    public function seek(int $offset, int $whence = SEEK_SET): void { $this->inner->seek($offset, $whence); }
    public function rewind(): void { $this->inner->rewind(); }
    public function isWritable(): bool { return $this->inner->isWritable(); }
    public function write(string $string): int { return $this->inner->write($string); }
    public function isReadable(): bool { return $this->inner->isReadable(); }

    public function read(int $length): string
    {
        $this->readLengths[] = $length;

        return $this->inner->read(min($length, $this->maximumReadSize));
    }

    public function getContents(): string { return $this->inner->getContents(); }
    public function getMetadata(?string $key = null): mixed { return $this->inner->getMetadata($key); }

    /** @return list<int> */
    public function readLengths(): array { return $this->readLengths; }
    public function stringCastCalls(): int { return $this->stringCastCalls; }
}
