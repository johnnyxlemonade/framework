<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Psr;

use Psr\Http\Message\StreamInterface;
use RuntimeException;

final class CallbackStream implements StreamInterface
{
    private bool $consumed = false;

    public function __construct(
        private readonly \Closure $producer,
    ) {}

    public static function from(callable $producer): self
    {
        return new self($producer(...));
    }

    public function __toString(): string
    {
        if ($this->consumed) {
            return '';
        }

        $this->consumed = true;

        ob_start();
        ($this->producer)();

        return (string) ob_get_clean();
    }

    public function close(): void {}

    public function detach()
    {
        return null;
    }

    public function getSize(): ?int
    {
        return null;
    }

    public function tell(): int
    {
        throw new RuntimeException('CallbackStream is not seekable.');
    }

    public function eof(): bool
    {
        return $this->consumed;
    }

    public function isSeekable(): bool
    {
        return false;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        throw new RuntimeException('CallbackStream is not seekable.');
    }

    public function rewind(): void
    {
        throw new RuntimeException('CallbackStream is not seekable.');
    }

    public function isWritable(): bool
    {
        return false;
    }

    public function write(string $string): int
    {
        throw new RuntimeException('CallbackStream is not writable.');
    }

    public function isReadable(): bool
    {
        return true;
    }

    public function read(int $length): string
    {
        throw new RuntimeException('CallbackStream does not support read().');
    }

    public function getContents(): string
    {
        return (string) $this;
    }

    public function getMetadata(?string $key = null): mixed
    {
        $meta = [
            'timed_out' => false,
            'blocked' => true,
            'eof' => $this->consumed,
            'stream_type' => 'callback',
            'mode' => 'r',
            'unread_bytes' => 0,
            'seekable' => false,
            'uri' => 'callback://stream',
        ];

        if ($key === null) {
            return $meta;
        }

        return $meta[$key] ?? null;
    }
}
