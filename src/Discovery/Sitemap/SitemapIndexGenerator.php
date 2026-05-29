<?php

declare(strict_types=1);

namespace Lemonade\Framework\Discovery\Sitemap;

use Lemonade\Framework\Support\Xml\XmlStreamWriter;

final class SitemapIndexGenerator
{
    /**
     * @param resource $stream
     * @param iterable<SitemapFile> $files
     */
    public function writeIndex($stream, iterable $files): void
    {
        $xml = new XmlStreamWriter($stream);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('sitemapindex', ['xmlns' => 'http://www.sitemaps.org/schemas/sitemap/0.9']);

        foreach ($files as $file) {
            $xml->startElement('sitemap');
            $xml->writeElement('loc', $file->publicUrl());
            if ($file->lastmod() !== null) {
                $xml->writeElement('lastmod', $this->formatLastmod($file->lastmod()));
            }
            $xml->endElement();
        }

        $xml->endElement();
        $xml->endDocument();
    }

    private function formatLastmod(\DateTimeInterface|string $lastmod): string
    {
        if ($lastmod instanceof \DateTimeInterface) {
            return $lastmod->format('Y-m-d');
        }

        return $lastmod;
    }
}
