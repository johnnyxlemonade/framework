<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Documentation;

use Lemonade\Framework\Api\Endpoint\ApiEndpointRegistry;
use Lemonade\Framework\Core\Config;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;

final class DocsController
{
    public function __construct(
        private readonly ApiEndpointRegistry $endpoints,
        private readonly Config $config,
        private readonly Psr17Factory $psr17,
    ) {}

    public function show(): ResponseInterface
    {
        $rows = '';
        foreach ($this->endpoints->all() as $endpoint) {
            $rows .= sprintf(
                '<tr><td><code>%s</code></td><td><code>%s%s</code></td><td>%s</td><td><code>%s</code></td></tr>',
                htmlspecialchars($endpoint->method(), ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($this->apiPrefix(), ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($endpoint->path(), ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($endpoint->summary(), ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($endpoint->name(), ENT_QUOTES, 'UTF-8'),
            );
        }

        $openApiUrl = $this->apiPrefix() . ($this->config->string('api.framework.openapi.route', '/framework/openapi.json') ?? '/framework/openapi.json');

        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Lemonade Framework API Docs</title></head><body>'
            . '<h1>Lemonade Framework API</h1>'
            . '<p><a href="' . htmlspecialchars($openApiUrl, ENT_QUOTES, 'UTF-8') . '">OpenAPI JSON</a></p>'
            . '<table border="1" cellspacing="0" cellpadding="6"><thead><tr><th>Method</th><th>Path</th><th>Summary</th><th>Name</th></tr></thead><tbody>'
            . $rows
            . '</tbody></table>'
            . '</body></html>';

        $response = $this->psr17
            ->createResponse(200)
            ->withHeader('Content-Type', 'text/html; charset=utf-8');

        $response->getBody()->write($html);

        return $response;
    }

    private function apiPrefix(): string
    {
        $prefix = $this->config->string('api.prefix', '/api') ?? '/api';
        $normalized = '/' . trim($prefix, '/');

        return $normalized === '/' ? '' : rtrim($normalized, '/');
    }
}
