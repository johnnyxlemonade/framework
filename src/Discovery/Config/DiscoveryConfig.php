<?php

declare(strict_types=1);

namespace Lemonade\Framework\Discovery\Config;

final class DiscoveryConfig
{
    public function __construct(
        public RobotsConfig $robots,
        public SitemapConfig $sitemap,
    ) {}
}
