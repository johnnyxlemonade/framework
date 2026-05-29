<?php

declare(strict_types=1);

namespace Lemonade\Framework\Discovery\Sitemap;

use DateTimeInterface;
use InvalidArgumentException;

final class SitemapUrl
{
    public function __construct(
        private readonly string $loc,
        private readonly DateTimeInterface|string|null $lastmod = null,
        private readonly SitemapChangeFrequency|string|null $changefreq = null,
        private readonly ?float $priority = null,
    ) {
        if (trim($this->loc) === '') {
            throw new InvalidArgumentException('Sitemap URL loc cannot be empty.');
        }

        if ($this->priority !== null && ($this->priority < 0.0 || $this->priority > 1.0)) {
            throw new InvalidArgumentException('Sitemap URL priority must be between 0.0 and 1.0.');
        }

        if (is_string($this->changefreq) && SitemapChangeFrequency::tryFrom($this->changefreq) === null) {
            throw new InvalidArgumentException(sprintf('Invalid sitemap changefreq "%s".', $this->changefreq));
        }
    }

    public static function create(
        string $loc,
        DateTimeInterface|string|null $lastmod = null,
        SitemapChangeFrequency|string|null $changefreq = null,
        ?float $priority = null,
    ): self {
        return new self($loc, $lastmod, $changefreq, $priority);
    }

    public function loc(): string
    {
        return $this->loc;
    }

    public function lastmod(): DateTimeInterface|string|null
    {
        return $this->lastmod;
    }

    public function changefreq(): SitemapChangeFrequency|string|null
    {
        return $this->changefreq;
    }

    public function priority(): ?float
    {
        return $this->priority;
    }
}
