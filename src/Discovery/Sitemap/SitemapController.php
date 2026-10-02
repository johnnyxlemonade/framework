<?php

declare(strict_types=1);

namespace Lemonade\Framework\Discovery\Sitemap;

use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Discovery\Config\SitemapConfig;
use Lemonade\Framework\Http\HttpStatus;
use Lemonade\Framework\Http\Response\Responses;
use Psr\Http\Message\ResponseInterface;

/**
 * Serves cached and generated sitemaps through bounded response body streams
 */
final class SitemapController
{
    /**
     * Initializes sitemap delivery with configuration, generation, paths and response factories
     */
    public function __construct(
        private readonly SitemapConfig $config,
        private readonly SitemapGenerator $generator,
        private readonly ApplicationContext $context,
        private readonly Responses $responses,
    ) {
    }

    /**
     * Returns the configured sitemap as a cached file or a lazily generated XML response
     */
    public function index(): ResponseInterface
    {
        if ($this->config->mode === 'cache') {
            $relativePath = $this->config->cachePath;
            $indexFilename = $this->config->indexFilename;
            $path = $this->context->basePath() . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath) . DIRECTORY_SEPARATOR . $indexFilename;

            if (!is_file($path)) {
                return $this->responses->text('', HttpStatus::NOT_FOUND->value);
            }

            $contentType = str_ends_with($path, '.gz')
                ? 'application/x-gzip'
                : 'application/xml; charset=UTF-8';

            $mtime = filemtime($path);
            if ($mtime === false) {
                $mtime = time();
            }

            $headers = ['Last-Modified' => gmdate('D, d M Y H:i:s', $mtime) . ' GMT'];

            return $this->responses->stream(static function () use ($path): iterable {
                $handle = fopen($path, 'rb');
                if (!is_resource($handle)) {
                    return;
                }
                while (!feof($handle)) {
                    $chunk = fread($handle, 8192);
                    if ($chunk === false) {
                        break;
                    }
                    yield $chunk;
                }
                fclose($handle);
            }, HttpStatus::OK->value, $contentType, $headers);
        }

        return $this->responses->stream(
            fn(): iterable => $this->generator->urlsetChunks($this->generator->urls()),
            HttpStatus::OK->value,
            'application/xml; charset=UTF-8',
        );
    }
}
