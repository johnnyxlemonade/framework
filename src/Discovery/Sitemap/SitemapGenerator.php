<?php

declare(strict_types=1);

namespace Lemonade\Framework\Discovery\Sitemap;

use Lemonade\Framework\Discovery\Config\SitemapConfig;
use Lemonade\Framework\Support\BaseUrlResolver;
use Lemonade\Framework\Support\Xml\XmlStreamWriter;
use Psr\Log\LoggerInterface;

final class SitemapGenerator
{
    public function __construct(
        private readonly SitemapProviderRegistry $registry,
        private readonly BaseUrlResolver $baseUrlResolver,
        private readonly SitemapConfig $config,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    /**
     * @return iterable<SitemapUrl>
     */
    public function urls(): iterable
    {
        $deduplicate = $this->config->deduplicate;
        $invalidMode = $this->config->onInvalidUrl;
        $baseUrl = $this->config->baseUrl;
        $seen = [];

        foreach ($this->registry->providers() as $provider) {
            foreach ($provider->urls() as $item) {
                try {
                    $loc = $this->normalizeLoc($item->loc(), $baseUrl);
                    if (filter_var($loc, FILTER_VALIDATE_URL) === false) {
                        throw new SitemapException(sprintf('Invalid URL "%s".', $loc));
                    }

                    if ($deduplicate) {
                        if (isset($seen[$loc])) {
                            continue;
                        }
                        $seen[$loc] = true;
                    }

                    yield new SitemapUrl($loc, $item->lastmod(), $item->changefreq(), $item->priority());
                } catch (\Throwable $exception) {
                    if ($invalidMode === 'skip') {
                        $this->logger?->warning($exception->getMessage(), ['source' => 'discovery.sitemap']);
                        continue;
                    }

                    throw $exception;
                }
            }
        }
    }

    /**
     * @param resource $stream
     * @param iterable<SitemapUrl> $urls
     * @return array{count:int}
     */
    public function writeUrlset($stream, iterable $urls): array
    {
        $xml = new XmlStreamWriter($stream);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('urlset', ['xmlns' => 'http://www.sitemaps.org/schemas/sitemap/0.9']);

        $count = 0;
        foreach ($urls as $url) {
            if (!$url instanceof SitemapUrl) {
                throw new SitemapException('Sitemap urlset expects SitemapUrl items.');
            }

            $this->writeUrlElement($xml, $url);
            $count++;
        }

        $xml->endElement();
        $xml->endDocument();

        return ['count' => $count];
    }

    public function startUrlset(XmlStreamWriter $xml): void
    {
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('urlset', ['xmlns' => 'http://www.sitemaps.org/schemas/sitemap/0.9']);
    }

    public function endUrlset(XmlStreamWriter $xml): void
    {
        $xml->endElement();
        $xml->endDocument();
    }

    public function writeUrlElement(XmlStreamWriter $xml, SitemapUrl $url): void
    {
        $xml->startElement('url');
        $xml->writeElement('loc', $url->loc());

        $lastmod = $url->lastmod();
        if ($lastmod !== null) {
            $xml->writeElement('lastmod', $this->formatLastmod($lastmod));
        }

        $changeFrequency = $url->changefreq();
        if ($changeFrequency !== null) {
            $changeFrequencyValue = $changeFrequency instanceof SitemapChangeFrequency
                ? $changeFrequency->value
                : $changeFrequency;

            $xml->writeElement('changefreq', $changeFrequencyValue);
        }

        $priority = $url->priority();
        if ($priority !== null) {
            $xml->writeElement('priority', number_format($priority, 1, '.', ''));
        }

        $xml->endElement();
    }

    private function normalizeLoc(string $loc, ?string $configuredBaseUrl): string
    {
        $trimmed = trim($loc);
        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
            return $trimmed;
        }

        if ($configuredBaseUrl !== null && trim($configuredBaseUrl) !== '') {
            return rtrim($configuredBaseUrl, '/') . '/' . ltrim($trimmed, '/');
        }

        return $this->baseUrlResolver->baseUrl($trimmed);
    }

    private function formatLastmod(\DateTimeInterface|string $lastmod): string
    {
        if ($lastmod instanceof \DateTimeInterface) {
            return $lastmod->format('Y-m-d');
        }

        return $lastmod;
    }
}
