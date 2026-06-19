<?php

declare(strict_types=1);

namespace Lemonade\Framework\Discovery\Config;

use Lemonade\Framework\Discovery\Sitemap\SitemapProviderInterface;

final class SitemapConfig
{
    /**
     * @param list<SitemapRouteConfig> $routes
     * @param list<class-string<SitemapProviderInterface>> $providers
     */
    public function __construct(
        public bool $enabled,
        public string $route,
        public bool $cliOutput,
        public string $mode,
        public ?string $baseUrl,
        public array $routes,
        public array $providers,
        public string $cachePath,
        public string $filename,
        public string $indexFilename,
        public bool $gzip,
        public int $maxUrlsPerFile,
        public int $maxUncompressedBytes,
        public bool $deduplicate,
        public string $onInvalidUrl,
    ) {}
}
