<?php

declare(strict_types=1);

namespace Lemonade\Framework\Discovery\Sitemap;

use Lemonade\Framework\Discovery\Config\SitemapConfig;
use Lemonade\Framework\Routing\Exception\MissingRouteParameterException;
use Lemonade\Framework\Routing\Exception\RouteNotFoundException;
use Lemonade\Framework\Routing\UrlGenerator;

final class RouteSitemapProvider implements SitemapProviderInterface
{
    public function __construct(
        private readonly SitemapConfig $config,
        private readonly UrlGenerator $urlGenerator,
    ) {}

    public function urls(): iterable
    {
        $mode = $this->config->onInvalidUrl;

        foreach ($this->config->routes as $item) {
            if (trim($item->name) === '') {
                if ($mode === 'skip') {
                    continue;
                }

                throw new SitemapException('Route sitemap item requires non-empty "name".');
            }

            try {
                yield SitemapUrl::create(
                    $this->urlGenerator->route($item->name, $item->params),
                    $item->lastmod,
                    $item->changefreq,
                    $item->priority,
                );
            } catch (RouteNotFoundException|MissingRouteParameterException|\InvalidArgumentException $exception) {
                if ($mode === 'skip') {
                    continue;
                }

                throw new SitemapException($exception->getMessage(), 0, $exception);
            }
        }
    }
}
