<?php

declare(strict_types=1);

use Lemonade\Framework\Discovery\Config\DiscoveryConfigDefinition;

return DiscoveryConfigDefinition::create()
    ->robotsDisabled()
    ->robotsRoute('/robots.txt')
    ->robotsHeaderEnabled()
    ->robotsHeaderGenerator('Lemonade Framework')
    ->robotsHeaderDateFormat('Y-m-d H:i:s')
    ->robotsRule('*', ['/'], [])
    ->robotsSitemap('/sitemap.xml')
    ->sitemapDisabled()
    ->sitemapRoute('/sitemap.xml')
    ->sitemapCliOutput()
    ->sitemapMode('stream')
    ->sitemapBaseUrl(null)
    ->sitemapRoutes([])
    ->sitemapProviders([])
    ->sitemapCachePath('storage/cache/discovery')
    ->sitemapFilename('sitemap.xml')
    ->sitemapIndexFilename('sitemap.xml')
    ->sitemapGzip(false)
    ->sitemapMaxUrlsPerFile(50000)
    ->sitemapMaxUncompressedBytes(52428800)
    ->sitemapDeduplicate(false)
    ->sitemapOnInvalidUrl('fail');
