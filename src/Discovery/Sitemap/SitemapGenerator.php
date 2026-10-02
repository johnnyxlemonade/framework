<?php

declare(strict_types=1);

namespace Lemonade\Framework\Discovery\Sitemap;

use Lemonade\Framework\Discovery\Config\SitemapConfig;
use Lemonade\Framework\Support\BaseUrlResolver;
use Lemonade\Framework\Support\Xml\XmlStreamWriter;
use Psr\Log\LoggerInterface;

/**
 * Normalizes sitemap URLs and serializes them either to a target stream or as lazy XML chunks
 */
final class SitemapGenerator
{
    /**
     * Initializes sitemap generation with provider discovery and URL normalization dependencies
     */
    public function __construct(
        private readonly SitemapProviderRegistry $registry,
        private readonly BaseUrlResolver $baseUrlResolver,
        private readonly SitemapConfig $config,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Lazily resolves valid sitemap URLs from registered providers
     *
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
     * Lazily yields XML urlset chunks without assembling the full sitemap in memory
     *
     * @param iterable<SitemapUrl> $urls
     * @return iterable<string>
     */
    public function urlsetChunks(iterable $urls): iterable
    {
        yield "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        yield '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($urls as $url) {
            if (!$url instanceof SitemapUrl) {
                throw new SitemapException('Sitemap urlset expects SitemapUrl items.');
            }

            yield $this->urlChunk($url);
        }

        yield "</urlset>\n";
    }

    /**
     * Writes a complete XML urlset to the supplied resource stream
     *
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

    /**
     * Writes the beginning of an XML urlset through the XML writer
     */
    public function startUrlset(XmlStreamWriter $xml): void
    {
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('urlset', ['xmlns' => 'http://www.sitemaps.org/schemas/sitemap/0.9']);
    }

    /**
     * Writes the end of an XML urlset through the XML writer
     */
    public function endUrlset(XmlStreamWriter $xml): void
    {
        $xml->endElement();
        $xml->endDocument();
    }

    /**
     * Writes one URL element with all available sitemap attributes
     */
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

    /**
     * Renders one XML URL element through a small temporary stream
     */
    private function urlChunk(SitemapUrl $url): string
    {
        $stream = fopen('php://temp', 'w+b');

        if (!is_resource($stream)) {
            throw new SitemapException('Unable to open temporary sitemap XML stream.');
        }

        try {
            $xml = new XmlStreamWriter($stream);
            $this->writeUrlElement($xml, $url);
            rewind($stream);
            $chunk = stream_get_contents($stream);

            if (!is_string($chunk)) {
                throw new SitemapException('Unable to read temporary sitemap XML stream.');
            }

            return $chunk;
        } finally {
            fclose($stream);
        }
    }

    /**
     * Normalizes a relative or absolute URL according to sitemap configuration
     */
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

    /**
     * Converts a last-modified value to the sitemap date format
     */
    private function formatLastmod(\DateTimeInterface|string $lastmod): string
    {
        if ($lastmod instanceof \DateTimeInterface) {
            return $lastmod->format('Y-m-d');
        }

        return $lastmod;
    }
}
