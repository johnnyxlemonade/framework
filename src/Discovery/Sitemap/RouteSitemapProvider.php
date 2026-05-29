<?php

declare(strict_types=1);

namespace Lemonade\Framework\Discovery\Sitemap;

use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Routing\Exception\MissingRouteParameterException;
use Lemonade\Framework\Routing\Exception\RouteNotFoundException;
use Lemonade\Framework\Routing\UrlGenerator;

final class RouteSitemapProvider implements SitemapProviderInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly UrlGenerator $urlGenerator,
    ) {}

    public function urls(): iterable
    {
        $items = $this->config->array('discovery.sitemap.routes');
        $mode = $this->config->string('discovery.sitemap.on_invalid_url', 'fail') ?? 'fail';

        foreach ($items as $item) {
            $name = null;
            $params = [];
            $lastmod = null;
            $changefreq = null;
            $priority = null;

            if (is_string($item)) {
                $name = $item;
            } elseif (is_array($item)) {
                $name = isset($item['name']) && is_string($item['name']) ? $item['name'] : null;
                if (isset($item['params']) && is_array($item['params'])) {
                    foreach ($item['params'] as $paramKey => $paramValue) {
                        if (!is_string($paramKey)) {
                            continue;
                        }
                        if (
                            is_string($paramValue)
                            || is_int($paramValue)
                            || is_float($paramValue)
                            || is_bool($paramValue)
                            || $paramValue === null
                        ) {
                            $params[$paramKey] = $paramValue;
                        }
                    }
                }
                $lastmod = $item['lastmod'] ?? null;
                $changefreq = $item['changefreq'] ?? null;
                if (isset($item['priority']) && is_int($item['priority'])) {
                    $priority = (float) $item['priority'];
                } elseif (isset($item['priority']) && is_float($item['priority'])) {
                    $priority = $item['priority'];
                }
            }

            if ($name === null || trim($name) === '') {
                if ($mode === 'skip') {
                    continue;
                }

                throw new SitemapException('Route sitemap item requires non-empty "name".');
            }

            $lastmodValue = null;
            if ($lastmod === null || is_string($lastmod) || $lastmod instanceof \DateTimeInterface) {
                $lastmodValue = $lastmod;
            }
            $changefreqValue = is_string($changefreq) ? $changefreq : null;

            try {
                yield SitemapUrl::create(
                    $this->urlGenerator->route($name, $params),
                    $lastmodValue,
                    $changefreqValue,
                    $priority,
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
