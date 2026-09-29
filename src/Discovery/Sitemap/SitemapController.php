<?php

declare(strict_types=1);

namespace Lemonade\Framework\Discovery\Sitemap;

use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Discovery\Config\SitemapConfig;
use Lemonade\Framework\Http\HttpStatus;
use Lemonade\Framework\Http\Response\Responses;
use Psr\Http\Message\ResponseInterface;

final class SitemapController
{
    public function __construct(
        private readonly SitemapConfig $config,
        private readonly SitemapGenerator $generator,
        private readonly ApplicationContext $context,
        private readonly Responses $responses,
    ) {
    }

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

            return $this->responses->stream(static function () use ($path): void {
                $handle = fopen($path, 'rb');
                if (!is_resource($handle)) {
                    return;
                }
                while (!feof($handle)) {
                    $chunk = fread($handle, 8192);
                    if ($chunk === false) {
                        break;
                    }
                    echo $chunk;
                }
                fclose($handle);
            }, HttpStatus::OK->value, $contentType, $headers);
        }

        return $this->responses->stream(function (): void {
            $stream = fopen('php://output', 'wb');
            if (!is_resource($stream)) {
                return;
            }

            $this->generator->writeUrlset($stream, $this->generator->urls());
            fclose($stream);
        }, HttpStatus::OK->value, 'application/xml; charset=UTF-8');
    }
}
