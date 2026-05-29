<?php

declare(strict_types=1);

namespace Lemonade\Framework\Support\Xml;

use RuntimeException;
use Throwable;
use XMLWriter;

final class XmlStreamWriter
{
    /** @var resource */
    private readonly mixed $stream;
    private readonly XMLWriter $writer;
    private int $bytesWritten = 0;

    /**
     * @param resource $stream
     */
    public function __construct(mixed $stream)
    {
        if (!class_exists(XMLWriter::class)) {
            throw new RuntimeException('PHP extension "xmlwriter" is required to use XmlStreamWriter.');
        }

        if (!is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new RuntimeException('XmlStreamWriter expects a valid stream resource.');
        }

        $this->stream = $stream;
        $this->writer = new XMLWriter();
        if ($this->writer->openMemory() !== true) {
            throw new RuntimeException('Failed to initialize XML writer memory buffer.');
        }
    }

    public function startDocument(string $version = '1.0', string $encoding = 'UTF-8'): void
    {
        $this->writer->startDocument($version, $encoding);
        $this->flush();
    }

    public function endDocument(): void
    {
        $this->writer->endDocument();
        $this->flush();
    }

    /**
     * @param array<string, string> $attributes
     */
    public function startElement(string $name, array $attributes = []): void
    {
        $this->writer->startElement($name);
        foreach ($attributes as $attribute => $value) {
            $this->writer->writeAttribute($attribute, $value);
        }
        $this->flush();
    }

    public function endElement(): void
    {
        $this->writer->endElement();
        $this->flush();
    }

    public function writeElement(string $name, string $value): void
    {
        $this->writer->writeElement($name, $value);
        $this->flush();
    }

    public function writeText(string $value): void
    {
        $this->writer->text($value);
        $this->flush();
    }

    public function flush(): void
    {
        $chunk = $this->writer->flush(true);
        if (!is_string($chunk)) {
            throw new RuntimeException('XML writer flush did not return a string chunk.');
        }

        if ($chunk === '') {
            return;
        }

        $this->writeFully($chunk);
    }

    public function bytesWritten(): int
    {
        return $this->bytesWritten;
    }

    private function writeFully(string $value): void
    {
        $length = strlen($value);
        $offset = 0;

        while ($offset < $length) {
            try {
                $written = fwrite($this->stream, substr($value, $offset));
            } catch (Throwable $exception) {
                throw new RuntimeException('Failed to write XML chunk to stream.', 0, $exception);
            }

            if ($written === false) {
                throw new RuntimeException('Failed to write XML chunk to stream.');
            }

            if ($written === 0) {
                throw new RuntimeException(sprintf(
                    'Failed to write XML chunk to stream. Stream accepted 0 bytes after %d of %d bytes.',
                    $offset,
                    $length,
                ));
            }

            $offset += $written;
            $this->bytesWritten += $written;
        }
    }
}
