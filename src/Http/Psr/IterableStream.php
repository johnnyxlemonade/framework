<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Psr;

use ArrayIterator;
use Iterator;
use IteratorIterator;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Represents a one-pass response body that reads lazy producer chunks without assembling the full content in memory
 */
final class IterableStream implements StreamInterface
{
    /**
     * @var Iterator<mixed, mixed>|null
     */
    private ?Iterator $chunks = null;
    private string $buffer = '';
    private bool $initialized = false;
    private bool $currentChunkLoaded = false;
    private bool $closed = false;
    private bool $eof = false;
    private int $position = 0;

    /**
     * Initializes a one-pass producer that lazily yields string chunks while the stream is read
     *
     * @param \Closure(): iterable<string> $producer
     */
    public function __construct(
        private readonly \Closure $producer,
    ) {
    }

    /**
     * Creates a body whose producer is not invoked until its first read
     *
     * @param callable(): iterable<string> $producer
     */
    public static function from(callable $producer): self
    {
        return new self($producer(...));
    }

    /**
     * Reads the remaining content for PSR-7 string conversion, which may use memory proportional to the body size
     */
    public function __toString(): string
    {
        try {
            return $this->getContents();
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Stops the producer and discards all unread chunks
     */
    public function close(): void
    {
        $this->closed = true;
        $this->eof = true;
        $this->chunks = null;
        $this->buffer = '';
    }

    /**
     * Detaches the one-pass producer and returns null because this body has no resource handle
     */
    public function detach(): null
    {
        $this->close();

        return null;
    }

    /**
     * Returns null because the producer total size is not known in advance
     */
    public function getSize(): ?int
    {
        return null;
    }

    /**
     * Returns the number of bytes read from this one-pass stream
     */
    public function tell(): int
    {
        return $this->position;
    }

    /**
     * Reports whether the producer has no remaining chunks or the stream was closed
     */
    public function eof(): bool
    {
        return $this->eof;
    }

    /**
     * Reports false because a generator producer cannot return to an earlier position
     */
    public function isSeekable(): bool
    {
        return false;
    }

    /**
     * Rejects repositioning within the one-pass stream
     */
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        throw new RuntimeException('IterableStream is not seekable.');
    }

    /**
     * Rejects rewinding the one-pass producer
     */
    public function rewind(): void
    {
        throw new RuntimeException('IterableStream is not seekable.');
    }

    /**
     * Reports false because response bodies created from producers do not accept writes
     */
    public function isWritable(): bool
    {
        return false;
    }

    /**
     * Rejects writes to the read-only response body
     */
    public function write(string $string): int
    {
        throw new RuntimeException('IterableStream is not writable.');
    }

    /**
     * Reports whether the stream remains open for reading
     */
    public function isReadable(): bool
    {
        return !$this->closed;
    }

    /**
     * Reads at most the requested byte count while retaining only the current chunk and its unread remainder
     */
    public function read(int $length): string
    {
        if ($length < 0) {
            throw new RuntimeException('Read length must not be negative.');
        }

        if ($this->closed || $length === 0) {
            return '';
        }

        $result = '';

        while (strlen($result) < $length) {
            if ($this->buffer === '') {
                $chunk = $this->nextChunk();

                if ($chunk === null) {
                    break;
                }

                $this->buffer = $chunk;
            }

            $remaining = $length - strlen($result);
            $bytesToRead = min($remaining, strlen($this->buffer));
            $result .= substr($this->buffer, 0, $bytesToRead);
            $this->buffer = substr($this->buffer, $bytesToRead);
        }

        $this->position += strlen($result);

        return $result;
    }

    /**
     * Reads all remaining chunks, a PSR-7 operation that may use memory proportional to the body size
     */
    public function getContents(): string
    {
        $contents = '';

        while (!$this->eof()) {
            $contents .= $this->read(65536);
        }

        return $contents;
    }

    /**
     * Returns metadata for the one-pass read-only producer
     */
    public function getMetadata(?string $key = null): mixed
    {
        $metadata = [
            'timed_out' => false,
            'blocked' => true,
            'eof' => $this->eof,
            'stream_type' => 'iterable',
            'mode' => 'r',
            'unread_bytes' => 0,
            'seekable' => false,
            'uri' => 'iterable://stream',
        ];

        if ($key === null) {
            return $metadata;
        }

        return $metadata[$key] ?? null;
    }

    /**
     * Returns the next non-empty chunk and advances the producer only on a subsequent read
     */
    private function nextChunk(): ?string
    {
        while (true) {
            $chunks = $this->chunks();

            if (!$this->currentChunkLoaded) {
                if ($this->initialized) {
                    $chunks->next();
                }

                $this->initialized = true;
                $this->currentChunkLoaded = true;
            }

            if (!$chunks->valid()) {
                $this->eof = true;

                return null;
            }

            $chunk = $chunks->current();
            $this->currentChunkLoaded = false;

            if (!is_string($chunk)) {
                throw new RuntimeException('IterableStream producer must yield only string chunks.');
            }

            if ($chunk !== '') {
                return $chunk;
            }
        }
    }

    /**
     * Initializes the producer iterator only on the first read attempt
     *
     * @return Iterator<mixed>
     */
    private function chunks(): Iterator
    {
        if ($this->chunks !== null) {
            return $this->chunks;
        }

        $chunks = ($this->producer)();

        if (!is_iterable($chunks)) {
            throw new RuntimeException('IterableStream producer must return an iterable of string chunks.');
        }

        if (is_array($chunks)) {
            $this->chunks = new ArrayIterator($chunks);

            return $this->chunks;
        }

        if ($chunks instanceof Iterator) {
            $this->chunks = $chunks;

            return $this->chunks;
        }

        $this->chunks = new IteratorIterator($chunks);

        return $this->chunks;
    }
}
