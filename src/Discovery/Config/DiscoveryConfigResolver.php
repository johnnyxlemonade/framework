<?php

declare(strict_types=1);

namespace Lemonade\Framework\Discovery\Config;

use Lemonade\Framework\Core\Config\Internal\ConfigValueNormalizer;
use Lemonade\Framework\Discovery\Sitemap\SitemapException;
use Lemonade\Framework\Discovery\Sitemap\SitemapProviderInterface;

final class DiscoveryConfigResolver
{
    public function resolve(DiscoveryConfigDefinition ...$definitions): DiscoveryConfig
    {
        $robotsEnabled = false;
        $robotsRoute = '/robots.txt';
        $robotsHeaderEnabled = true;
        $robotsHeaderGenerator = 'Lemonade Framework';
        $robotsHeaderDateFormat = 'Y-m-d H:i:s';
        $robotsRules = [new RobotsRuleConfig('*', ['/'], [])];
        $robotsSitemaps = ['/sitemap.xml'];

        $sitemapEnabled = false;
        $sitemapRoute = '/sitemap.xml';
        $sitemapCliOutput = true;
        $sitemapMode = 'stream';
        $sitemapBaseUrl = null;
        $sitemapRoutes = [];
        $sitemapProviders = [];
        $sitemapCachePath = 'storage/cache/discovery';
        $sitemapFilename = 'sitemap.xml';
        $sitemapIndexFilename = 'sitemap.xml';
        $sitemapGzip = false;
        $sitemapMaxUrlsPerFile = 50000;
        $sitemapMaxUncompressedBytes = 52428800;
        $sitemapDeduplicate = false;
        $sitemapOnInvalidUrl = 'fail';

        foreach ($definitions as $definition) {
            $data = $definition->toArray();
            $robots = ConfigValueNormalizer::assoc($data['robots'] ?? null);
            $sitemap = ConfigValueNormalizer::assoc($data['sitemap'] ?? null);

            if (array_key_exists('enabled', $robots)) {
                $robotsEnabled = ConfigValueNormalizer::boolOr($robots['enabled'], $robotsEnabled);
            }

            if (array_key_exists('route', $robots)) {
                $robotsRoute = ConfigValueNormalizer::nonEmptyStringOr($robots['route'], $robotsRoute);
            }

            $header = ConfigValueNormalizer::assoc($robots['header'] ?? null);
            if (array_key_exists('enabled', $header)) {
                $robotsHeaderEnabled = ConfigValueNormalizer::boolOr(
                    $header['enabled'],
                    $robotsHeaderEnabled,
                );
            }

            if (array_key_exists('generator', $header)) {
                $robotsHeaderGenerator = ConfigValueNormalizer::nonEmptyStringOr(
                    $header['generator'],
                    $robotsHeaderGenerator,
                );
            }

            if (array_key_exists('date_format', $header)) {
                $robotsHeaderDateFormat = ConfigValueNormalizer::nonEmptyStringOr(
                    $header['date_format'],
                    $robotsHeaderDateFormat,
                );
            }

            if (array_key_exists('rules', $robots)) {
                $robotsRules = $this->normalizeRobotsRules($robots['rules']);
            }

            if (array_key_exists('sitemaps', $robots)) {
                $robotsSitemaps = ConfigValueNormalizer::stringList($robots['sitemaps']);
            }

            if (array_key_exists('enabled', $sitemap)) {
                $sitemapEnabled = ConfigValueNormalizer::boolOr($sitemap['enabled'], $sitemapEnabled);
            }

            if (array_key_exists('route', $sitemap)) {
                $sitemapRoute = ConfigValueNormalizer::nonEmptyStringOr($sitemap['route'], $sitemapRoute);
            }

            if (array_key_exists('cli_output', $sitemap)) {
                $sitemapCliOutput = ConfigValueNormalizer::boolOr(
                    $sitemap['cli_output'],
                    $sitemapCliOutput,
                );
            }

            if (array_key_exists('mode', $sitemap)) {
                $sitemapMode = $this->normalizeMode($sitemap['mode'], ['stream', 'cache'], $sitemapMode);
            }

            if (array_key_exists('base_url', $sitemap)) {
                $sitemapBaseUrl = ConfigValueNormalizer::nullableString($sitemap['base_url']);
            }

            if (array_key_exists('routes', $sitemap)) {
                $sitemapRoutes = $this->normalizeSitemapRoutes($sitemap['routes']);
            }

            if (array_key_exists('providers', $sitemap)) {
                $sitemapProviders = $this->normalizeProviderClasses($sitemap['providers']);
            }

            if (array_key_exists('cache_path', $sitemap)) {
                $sitemapCachePath = ConfigValueNormalizer::nonEmptyStringOr(
                    $sitemap['cache_path'],
                    $sitemapCachePath,
                );
            }

            if (array_key_exists('filename', $sitemap)) {
                $sitemapFilename = ConfigValueNormalizer::nonEmptyStringOr(
                    $sitemap['filename'],
                    $sitemapFilename,
                );
            }

            if (array_key_exists('index_filename', $sitemap)) {
                $sitemapIndexFilename = ConfigValueNormalizer::nonEmptyStringOr(
                    $sitemap['index_filename'],
                    $sitemapIndexFilename,
                );
            }

            if (array_key_exists('gzip', $sitemap)) {
                $sitemapGzip = ConfigValueNormalizer::boolOr($sitemap['gzip'], $sitemapGzip);
            }

            if (array_key_exists('max_urls_per_file', $sitemap)) {
                $sitemapMaxUrlsPerFile = max(
                    1,
                    ConfigValueNormalizer::intOr($sitemap['max_urls_per_file'], $sitemapMaxUrlsPerFile),
                );
            }

            if (array_key_exists('max_uncompressed_bytes', $sitemap)) {
                $sitemapMaxUncompressedBytes = max(
                    1,
                    ConfigValueNormalizer::intOr(
                        $sitemap['max_uncompressed_bytes'],
                        $sitemapMaxUncompressedBytes,
                    ),
                );
            }

            if (array_key_exists('deduplicate', $sitemap)) {
                $sitemapDeduplicate = ConfigValueNormalizer::boolOr(
                    $sitemap['deduplicate'],
                    $sitemapDeduplicate,
                );
            }

            if (array_key_exists('on_invalid_url', $sitemap)) {
                $sitemapOnInvalidUrl = $this->normalizeMode($sitemap['on_invalid_url'], ['fail', 'skip'], $sitemapOnInvalidUrl);
            }
        }

        return new DiscoveryConfig(
            new RobotsConfig(
                enabled: $robotsEnabled,
                route: $robotsRoute,
                header: new RobotsHeaderConfig(
                    enabled: $robotsHeaderEnabled,
                    generator: $robotsHeaderGenerator,
                    dateFormat: $robotsHeaderDateFormat,
                ),
                rules: $robotsRules,
                sitemaps: $robotsSitemaps,
            ),
            new SitemapConfig(
                enabled: $sitemapEnabled,
                route: $sitemapRoute,
                cliOutput: $sitemapCliOutput,
                mode: $sitemapMode,
                baseUrl: $sitemapBaseUrl,
                routes: $sitemapRoutes,
                providers: $sitemapProviders,
                cachePath: $sitemapCachePath,
                filename: $sitemapFilename,
                indexFilename: $sitemapIndexFilename,
                gzip: $sitemapGzip,
                maxUrlsPerFile: $sitemapMaxUrlsPerFile,
                maxUncompressedBytes: $sitemapMaxUncompressedBytes,
                deduplicate: $sitemapDeduplicate,
                onInvalidUrl: $sitemapOnInvalidUrl,
            ),
        );
    }

    /**
     * @param list<string> $allowed
     */
    private function normalizeMode(mixed $value, array $allowed, string $default): string
    {
        $normalized = ConfigValueNormalizer::nullableString($value);

        if ($normalized === null || !in_array($normalized, $allowed, true)) {
            return $default;
        }

        return $normalized;
    }

    /**
     * @return list<RobotsRuleConfig>
     */
    private function normalizeRobotsRules(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $rules = [];

        foreach ($value as $agent => $rule) {
            if (!is_string($agent) || trim($agent) === '' || !is_array($rule)) {
                continue;
            }

            $rules[] = new RobotsRuleConfig(
                agent: trim($agent),
                allow: ConfigValueNormalizer::stringList($rule['allow'] ?? []),
                disallow: ConfigValueNormalizer::stringList($rule['disallow'] ?? []),
            );
        }

        return $rules;
    }

    /**
     * @return list<SitemapRouteConfig>
     */
    private function normalizeSitemapRoutes(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $routes = [];

        foreach ($value as $item) {
            if (is_string($item)) {
                $name = trim($item);

                if ($name !== '') {
                    $routes[] = new SitemapRouteConfig($name, [], null, null, null);
                }

                continue;
            }

            if (!is_array($item)) {
                continue;
            }

            $name = isset($item['name']) && is_scalar($item['name']) ? trim((string) $item['name']) : '';
            if ($name === '') {
                continue;
            }

            $params = [];
            $rawParams = $item['params'] ?? [];
            if (is_array($rawParams)) {
                foreach ($rawParams as $key => $paramValue) {
                    if (
                        is_string($key)
                        && (
                            is_string($paramValue)
                            || is_int($paramValue)
                            || is_float($paramValue)
                            || is_bool($paramValue)
                            || $paramValue === null
                        )
                    ) {
                        $params[$key] = $paramValue;
                    }
                }
            }

            $lastmod = $item['lastmod'] ?? null;
            if (!$lastmod instanceof \DateTimeInterface && !is_string($lastmod) && $lastmod !== null) {
                $lastmod = null;
            }

            $changefreq = isset($item['changefreq']) && is_scalar($item['changefreq'])
                ? trim((string) $item['changefreq'])
                : null;
            $priority = null;
            if (isset($item['priority']) && is_int($item['priority'])) {
                $priority = (float) $item['priority'];
            } elseif (isset($item['priority']) && is_float($item['priority'])) {
                $priority = $item['priority'];
            }

            $routes[] = new SitemapRouteConfig($name, $params, $lastmod, $changefreq !== null && $changefreq !== '' ? $changefreq : null, $priority);
        }

        return $routes;
    }

    /**
     * @return list<class-string<SitemapProviderInterface>>
     */
    private function normalizeProviderClasses(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $providers = [];

        foreach ($value as $providerClass) {
            if (!is_string($providerClass)) {
                continue;
            }

            $providerClass = trim($providerClass);
            if ($providerClass === '' || in_array($providerClass, $providers, true)) {
                continue;
            }

            if (!class_exists($providerClass)) {
                throw new SitemapException(sprintf('Sitemap provider class "%s" does not exist.', $providerClass));
            }

            if (!is_a($providerClass, SitemapProviderInterface::class, true)) {
                throw new SitemapException(sprintf(
                    'Sitemap provider "%s" must implement %s.',
                    $providerClass,
                    SitemapProviderInterface::class,
                ));
            }

            $providers[] = $providerClass;
        }

        return $providers;
    }
}
