<?php

declare(strict_types=1);

namespace Lemonade\Framework\Discovery\Sitemap;

use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Controller;
use Psr\Http\Message\ResponseInterface;

final class SitemapController extends Controller
{
    public function __construct(
        private readonly Config $config,
        private readonly SitemapGenerator $generator,
        private readonly ApplicationContext $context,
    ) {}

    public function index(): ResponseInterface
    {
        $mode = $this->config->string('discovery.sitemap.mode', 'stream') ?? 'stream';

        if ($mode === 'cache') {
            $relativePath = $this->config->string('discovery.sitemap.cache_path', 'storage/cache/discovery') ?? 'storage/cache/discovery';
            $indexFilename = $this->config->string('discovery.sitemap.index_filename', 'sitemap.xml') ?? 'sitemap.xml';
            $path = $this->context->basePath() . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath) . DIRECTORY_SEPARATOR . $indexFilename;

            if (!is_file($path)) {
                return $this->response('', 404, 'text/plain; charset=UTF-8');
            }

            $contentType = str_ends_with($path, '.gz')
                ? 'application/x-gzip'
                : 'application/xml; charset=UTF-8';

            $mtime = filemtime($path);
            if ($mtime === false) {
                $mtime = time();
            }

            $headers = ['Last-Modified' => gmdate('D, d M Y H:i:s', $mtime) . ' GMT'];

            return $this->stream(static function () use ($path): void {
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
            }, 200, $contentType, $headers);
        }

        return $this->stream(function (): void {
            $stream = fopen('php://output', 'wb');
            if (!is_resource($stream)) {
                return;
            }

            $this->generator->writeUrlset($stream, $this->generator->urls());
            fclose($stream);
        }, 200, 'application/xml; charset=UTF-8');
    }
}
