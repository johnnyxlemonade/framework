<?php

declare(strict_types=1);

namespace Lemonade\Framework\Discovery\Sitemap;

final class SitemapGenerationResult
{
    /**
     * @param list<SitemapFile> $files
     */
    public function __construct(
        private readonly int $urlCount,
        private readonly array $files,
        private readonly SitemapFile $indexFile,
        private readonly bool $gzip,
        private readonly string $outputPath,
    ) {}

    public function urlCount(): int
    {
        return $this->urlCount;
    }

    /**
     * @return list<SitemapFile>
     */
    public function files(): array
    {
        return $this->files;
    }

    public function indexFile(): SitemapFile
    {
        return $this->indexFile;
    }

    public function gzip(): bool
    {
        return $this->gzip;
    }

    public function outputPath(): string
    {
        return $this->outputPath;
    }
}
