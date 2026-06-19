<?php

declare(strict_types=1);

namespace Lemonade\Framework\Discovery\Config;

use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;
use Lemonade\Framework\Discovery\Sitemap\SitemapProviderInterface;

final class DiscoveryConfigDefinition extends AbstractConfigDefinition
{
    public static function create(): self
    {
        return new self();
    }

    public static function moduleKey(): string
    {
        return 'discovery';
    }

    public function robotsEnabled(bool $enabled = true): self
    {
        return $this->set('robots.enabled', $enabled);
    }

    public function robotsDisabled(): self
    {
        return $this->robotsEnabled(false);
    }

    public function robotsRoute(string $route): self
    {
        return $this->set('robots.route', $route);
    }

    public function robotsHeaderEnabled(bool $enabled = true): self
    {
        return $this->set('robots.header.enabled', $enabled);
    }

    public function robotsHeaderDisabled(): self
    {
        return $this->robotsHeaderEnabled(false);
    }

    public function robotsHeaderGenerator(string $generator): self
    {
        return $this->set('robots.header.generator', $generator);
    }

    public function robotsHeaderDateFormat(string $dateFormat): self
    {
        return $this->set('robots.header.date_format', $dateFormat);
    }

    /**
     * @param list<string|int|float|bool> $allow
     * @param list<string|int|float|bool> $disallow
     */
    public function robotsRule(string $agent, array $allow = [], array $disallow = []): self
    {
        $agent = trim($agent);

        if ($agent === '') {
            return $this;
        }

        if (!isset($this->data['robots']) || !is_array($this->data['robots'])) {
            $this->data['robots'] = [];
        }

        $robots = $this->data['robots'];

        $rules = $robots['rules'] ?? [];
        if (!is_array($rules)) {
            $rules = [];
        }

        $rules[$agent] = [
            'allow' => array_values($allow),
            'disallow' => array_values($disallow),
        ];
        $robots['rules'] = $rules;
        $this->data['robots'] = $robots;

        return $this;
    }

    /**
     * @param list<string|int|float|bool> $sitemaps
     */
    public function robotsSitemaps(array $sitemaps): self
    {
        return $this->set('robots.sitemaps', array_values($sitemaps));
    }

    public function robotsSitemap(string $sitemap): self
    {
        return $this->append('robots.sitemaps', $sitemap);
    }

    public function sitemapEnabled(bool $enabled = true): self
    {
        return $this->set('sitemap.enabled', $enabled);
    }

    public function sitemapDisabled(): self
    {
        return $this->sitemapEnabled(false);
    }

    public function sitemapRoute(string $route): self
    {
        return $this->set('sitemap.route', $route);
    }

    public function sitemapCliOutput(bool $enabled = true): self
    {
        return $this->set('sitemap.cli_output', $enabled);
    }

    public function sitemapMode(string $mode): self
    {
        return $this->set('sitemap.mode', $mode);
    }

    public function sitemapBaseUrl(?string $baseUrl): self
    {
        return $this->set('sitemap.base_url', $baseUrl);
    }

    /**
     * @param array<string, scalar|null> $params
     */
    public function sitemapRouteItem(
        string $name,
        array $params = [],
        string|\DateTimeInterface|null $lastmod = null,
        ?string $changefreq = null,
        ?float $priority = null,
    ): self {
        $item = ['name' => $name];

        if ($params !== []) {
            $item['params'] = $params;
        }

        if ($lastmod !== null) {
            $item['lastmod'] = $lastmod;
        }

        if ($changefreq !== null) {
            $item['changefreq'] = $changefreq;
        }

        if ($priority !== null) {
            $item['priority'] = $priority;
        }

        return $this->append('sitemap.routes', $item);
    }

    /**
     * @param list<string|array<string, mixed>> $routes
     */
    public function sitemapRoutes(array $routes): self
    {
        return $this->set('sitemap.routes', array_values($routes));
    }

    /**
     * @param class-string<SitemapProviderInterface> $providerClass
     */
    public function sitemapProvider(string $providerClass): self
    {
        return $this->append('sitemap.providers', $providerClass);
    }

    /**
     * @param list<class-string<SitemapProviderInterface>|string> $providerClasses
     */
    public function sitemapProviders(array $providerClasses): self
    {
        return $this->set('sitemap.providers', array_values($providerClasses));
    }

    public function sitemapCachePath(string $cachePath): self
    {
        return $this->set('sitemap.cache_path', $cachePath);
    }

    public function sitemapFilename(string $filename): self
    {
        return $this->set('sitemap.filename', $filename);
    }

    public function sitemapIndexFilename(string $filename): self
    {
        return $this->set('sitemap.index_filename', $filename);
    }

    public function sitemapGzip(bool $gzip = true): self
    {
        return $this->set('sitemap.gzip', $gzip);
    }

    public function sitemapMaxUrlsPerFile(int $maxUrlsPerFile): self
    {
        return $this->set('sitemap.max_urls_per_file', $maxUrlsPerFile);
    }

    public function sitemapMaxUncompressedBytes(int $maxUncompressedBytes): self
    {
        return $this->set('sitemap.max_uncompressed_bytes', $maxUncompressedBytes);
    }

    public function sitemapDeduplicate(bool $deduplicate = true): self
    {
        return $this->set('sitemap.deduplicate', $deduplicate);
    }

    public function sitemapOnInvalidUrl(string $mode): self
    {
        return $this->set('sitemap.on_invalid_url', $mode);
    }
}
