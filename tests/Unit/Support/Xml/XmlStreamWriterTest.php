<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Support\Xml;

use Lemonade\Framework\Support\Xml\XmlStreamWriter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class XmlStreamWriterTest extends TestCase
{
    public function testWritesValidXmlToTempStreamAndIsGeneric(): void
    {
        $stream = fopen('php://temp', 'w+b');
        self::assertIsResource($stream);

        $xml = new XmlStreamWriter($stream);
        $xml->startDocument();
        $xml->startElement('feed', ['version' => '1', 'meta' => 'A&B <C> "D"']);
        $xml->writeElement('title', 'A&B <C> "D" \'E\'');
        $xml->startElement('description');
        $xml->writeText('X&Y <Z> "Q" \'R\'');
        $xml->endElement();
        $xml->endElement();
        $xml->endDocument();

        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        self::assertIsString($content);
        self::assertStringContainsString('<feed version="1" meta="A&amp;B &lt;C&gt; &quot;D&quot;">', $content);
        self::assertStringContainsString('meta="A&amp;B &lt;C&gt; &quot;D&quot;"', $content);
        self::assertStringContainsString('&amp;', $content);
        self::assertStringContainsString('&lt;', $content);
        self::assertStringContainsString('&quot;', $content);
        self::assertStringContainsString("'E'", $content);
        self::assertStringContainsString('<description>X&amp;Y &lt;Z&gt; &quot;Q&quot; \'R\'</description>', $content);
        self::assertSame(strlen($content), $xml->bytesWritten());
    }

    public function testConstructorRejectsNonResource(): void
    {
        /** @var mixed $invalid */
        $invalid = 'not-a-resource';

        $this->expectException(RuntimeException::class);
        /** @phpstan-ignore-next-line intentionally passing non-resource to verify runtime guard */
        new XmlStreamWriter($invalid);
    }

    public function testConstructorRejectsNonStreamResource(): void
    {
        $resource = stream_context_create();
        self::assertSame('stream-context', get_resource_type($resource));

        $this->expectException(RuntimeException::class);
        new XmlStreamWriter($resource);
    }

    public function testConstructorRejectsClosedStream(): void
    {
        $stream = fopen('php://temp', 'w+b');
        self::assertIsResource($stream);
        fclose($stream);

        $this->expectException(RuntimeException::class);
        new XmlStreamWriter($stream);
    }

    public function testFlushFailsOnClosedStream(): void
    {
        $stream = fopen('php://temp', 'w+b');
        self::assertIsResource($stream);

        $xml = new XmlStreamWriter($stream);
        fclose($stream);

        $this->expectException(RuntimeException::class);
        $xml->startDocument();
    }
}
