<?php

declare(strict_types=1);

namespace Lemonade\Framework\Discovery\Sitemap;

use DateTimeInterface;

final class SitemapFile
{
    public function __construct(
        private readonly string $path,
        private readonly string $publicUrl,
        private readonly DateTimeInterface|string|null $lastmod = null,
    ) {}

    public function path(): string
    {
        return $this->path;
    }

    public function publicUrl(): string
    {
        return $this->publicUrl;
    }

    public function lastmod(): DateTimeInterface|string|null
    {
        return $this->lastmod;
    }
}
