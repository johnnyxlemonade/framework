<?php

declare(strict_types=1);

namespace Lemonade\Framework\Discovery\Sitemap;

interface SitemapProviderInterface
{
    /**
     * @return iterable<SitemapUrl>
     */
    public function urls(): iterable;
}

