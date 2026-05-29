<?php

declare(strict_types=1);

namespace Lemonade\Framework\Discovery\Sitemap;

use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Filesystem\Contract\DirectoryManagerInterface;
use Lemonade\Framework\Support\Xml\XmlStreamWriter;
use Psr\Log\LoggerInterface;
use Throwable;

final class SitemapFileGenerator
{
    public function __construct(
        private readonly SitemapGenerator $generator,
        private readonly SitemapIndexGenerator $indexGenerator,
        private readonly Config $config,
        private readonly ApplicationContext $context,
        private readonly DirectoryManagerInterface $directories,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    public function generate(): SitemapGenerationResult
    {
        $relativePath = $this->config->string('discovery.sitemap.cache_path', 'storage/cache/discovery') ?? 'storage/cache/discovery';
        $outputPath = $this->context->basePath() . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
        $this->directories->create($outputPath);

        $maxUrls = max(1, $this->config->int('discovery.sitemap.max_urls_per_file', 50000));
        $maxBytes = max(1, $this->config->int('discovery.sitemap.max_uncompressed_bytes', 52428800));
        $baseFilename = $this->config->string('discovery.sitemap.filename', 'sitemap.xml') ?? 'sitemap.xml';
        $indexFilename = $this->config->string('discovery.sitemap.index_filename', 'sitemap.xml') ?? 'sitemap.xml';
        $gzip = $this->config->bool('discovery.sitemap.gzip', false);
        $baseUrl = rtrim($this->config->string('discovery.sitemap.base_url') ?? '', '/');
        $lastmod = date('Y-m-d');

        /** @var list<SitemapFile> $files */
        $files = [];
        /** @var list<string> $tempPaths */
        $tempPaths = [];
        $totalCount = 0;
        $part = 1;
        $partCount = 0;

        $current = $this->openPart($outputPath, $baseFilename, $part);
        $tempPaths[] = $current['tmpPath'];

        try {
            foreach ($this->generator->urls() as $url) {
                $this->generator->writeUrlElement($current['xml'], $url);
                $partCount++;
                $totalCount++;

                if ($partCount >= $maxUrls || $current['xml']->bytesWritten() >= $maxBytes) {
                    $files[] = $this->closePart($current, $gzip, $baseUrl, $lastmod);
                    $part++;
                    $partCount = 0;
                    $current = $this->openPart($outputPath, $baseFilename, $part);
                    $tempPaths[] = $current['tmpPath'];
                }
            }

            if ($partCount > 0 || $files === []) {
                $files[] = $this->closePart($current, $gzip, $baseUrl, $lastmod);
            } else {
                $this->closeAndRemoveEmptyPart($current);
            }

            $indexPath = $outputPath . DIRECTORY_SEPARATOR . $indexFilename;
            if (count($files) === 1 && basename($files[0]->path()) === $indexFilename) {
                $indexFile = $files[0];
            } else {
                $tmpPath = $indexPath . '.tmp';
                $tempPaths[] = $tmpPath;
                $stream = fopen($tmpPath, 'wb');
                if (!is_resource($stream)) {
                    throw new SitemapException(sprintf('Unable to open sitemap index temp file "%s".', $tmpPath));
                }
                $this->indexGenerator->writeIndex($stream, $files);
                fclose($stream);
                rename($tmpPath, $indexPath);
                $indexFile = new SitemapFile($indexPath, $this->toPublicUrl($baseUrl, basename($indexPath)), $lastmod);
            }

            $this->logger?->info('Sitemap cache generated.', ['urls' => $totalCount, 'files' => count($files), 'path' => $outputPath]);

            return new SitemapGenerationResult($totalCount, $files, $indexFile, $gzip, $outputPath);
        } catch (Throwable $exception) {
            foreach ($tempPaths as $tmpPath) {
                if (is_file($tmpPath)) {
                    @unlink($tmpPath);
                }
                if (is_file($tmpPath . '.xml')) {
                    @unlink($tmpPath . '.xml');
                }
            }

            throw $exception;
        }
    }

    /**
     * @return array{tmpPath:string,finalPath:string,stream:resource,xml:XmlStreamWriter}
     */
    private function openPart(string $outputPath, string $baseFilename, int $part): array
    {
        $filename = $part === 1 ? $baseFilename : preg_replace('/\.xml$/', '-' . $part . '.xml', $baseFilename);
        $filename = is_string($filename) ? $filename : $baseFilename . '-' . $part . '.xml';
        $finalPath = $outputPath . DIRECTORY_SEPARATOR . $filename;
        $tmpPath = $finalPath . '.tmp';

        $stream = fopen($tmpPath, 'wb');
        if (!is_resource($stream)) {
            throw new SitemapException(sprintf('Unable to open sitemap temp file "%s".', $tmpPath));
        }

        $xml = new XmlStreamWriter($stream);
        $this->generator->startUrlset($xml);

        return [
            'tmpPath' => $tmpPath,
            'finalPath' => $finalPath,
            'stream' => $stream,
            'xml' => $xml,
        ];
    }

    /**
     * @param array{tmpPath:string,finalPath:string,stream:resource,xml:XmlStreamWriter} $part
     */
    private function closePart(array $part, bool $gzip, string $baseUrl, string $lastmod): SitemapFile
    {
        $this->generator->endUrlset($part['xml']);
        fclose($part['stream']);

        if ($gzip) {
            $gzTmp = $part['tmpPath'] . '.gz';
            $gz = gzopen($gzTmp, 'wb6');
            if ($gz === false) {
                throw new SitemapException(sprintf('Unable to open gzip temp file "%s".', $gzTmp));
            }
            $source = fopen($part['tmpPath'], 'rb');
            if (!is_resource($source)) {
                gzclose($gz);
                throw new SitemapException(sprintf('Unable to read sitemap temp file "%s".', $part['tmpPath']));
            }
            while (!feof($source)) {
                $chunk = fread($source, 8192);
                if ($chunk === false) {
                    break;
                }
                gzwrite($gz, $chunk);
            }
            fclose($source);
            gzclose($gz);
            @unlink($part['tmpPath']);

            $finalPath = $part['finalPath'] . '.gz';
            rename($gzTmp, $finalPath);

            return new SitemapFile($finalPath, $this->toPublicUrl($baseUrl, basename($finalPath)), $lastmod);
        }

        rename($part['tmpPath'], $part['finalPath']);

        return new SitemapFile($part['finalPath'], $this->toPublicUrl($baseUrl, basename($part['finalPath'])), $lastmod);
    }

    /**
     * @param array{tmpPath:string,finalPath:string,stream:resource,xml:XmlStreamWriter} $part
     */
    private function closeAndRemoveEmptyPart(array $part): void
    {
        $this->generator->endUrlset($part['xml']);
        fclose($part['stream']);
        if (is_file($part['tmpPath'])) {
            @unlink($part['tmpPath']);
        }
    }

    private function toPublicUrl(string $baseUrl, string $filename): string
    {
        if ($baseUrl === '') {
            return '/' . ltrim($filename, '/');
        }

        return $baseUrl . '/' . ltrim($filename, '/');
    }
}
