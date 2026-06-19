<?php

declare(strict_types=1);

namespace Lemonade\Framework\Discovery\Config;

final class SitemapRouteConfig
{
    /**
     * @param array<string, scalar|null> $params
     */
    public function __construct(
        public string $name,
        public array $params,
        public string|\DateTimeInterface|null $lastmod,
        public ?string $changefreq,
        public ?float $priority,
    ) {}
}
