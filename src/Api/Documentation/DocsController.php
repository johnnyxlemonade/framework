<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Documentation;

use Lemonade\Framework\Api\Config\ApiConfig;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;

final class DocsController
{
    private const SWAGGER_UI_VERSION = '5.32.14';
    private const SWAGGER_ASSET_BASE_URL = 'https://static.lemonadeframework.cz/swagger';

    public function __construct(
        private readonly ApiConfig $config,
        private readonly Psr17Factory $psr17,
    ) {}

    public function show(): ResponseInterface
    {
        $openApiUrl = $this->joinPath(
            $this->apiPrefix(),
            $this->config->framework->openapi->route,
        );
        $openApiJson = json_encode($openApiUrl, JSON_THROW_ON_ERROR);
        $swaggerCssUrl = $this->swaggerUiAssetUrl('swagger-ui.css');
        $swaggerThemeCssUrl = $this->swaggerUiAssetUrl('swagger-docs-lemonade.css');
        $swaggerBundleUrl = $this->swaggerUiAssetUrl('swagger-ui-bundle.js');
        $swaggerPresetUrl = $this->swaggerUiAssetUrl('swagger-ui-standalone-preset.js');

        $html = '<!doctype html><html lang="en"><head>'
            . '<meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Lemonade Framework API Docs</title>'
            . '<script>(()=>{const storageKey="lf-theme";const storedTheme=window.localStorage.getItem(storageKey);const theme=storedTheme==="light"||storedTheme==="dark"?storedTheme:"dark";document.documentElement.setAttribute("data-bs-theme",theme);})();</script>'
            . '<link rel="stylesheet" href="' . htmlspecialchars($swaggerCssUrl, ENT_QUOTES, 'UTF-8') . '">'
            . '<link rel="stylesheet" href="' . htmlspecialchars($swaggerThemeCssUrl, ENT_QUOTES, 'UTF-8') . '">'
            . '<style>html{box-sizing:border-box;overflow-y:scroll}*,*:before,*:after{box-sizing:inherit}body{margin:0}.lf-openapi-link{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1rem;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.lf-theme-toggle{padding:.5rem .85rem;border:1px solid currentColor;background:transparent;color:inherit;font:inherit;cursor:pointer}#swagger-ui{min-height:calc(100vh - 4.5rem)}</style>'
            . '</head><body>'
            . '<p class="lf-openapi-link"><a href="' . htmlspecialchars($openApiUrl, ENT_QUOTES, 'UTF-8') . '">OpenAPI JSON</a><button type="button" class="lf-theme-toggle" data-theme-toggle>Toggle theme</button></p>'
            . '<div id="swagger-ui"></div>'
            . '<script src="' . htmlspecialchars($swaggerBundleUrl, ENT_QUOTES, 'UTF-8') . '"></script>'
            . '<script src="' . htmlspecialchars($swaggerPresetUrl, ENT_QUOTES, 'UTF-8') . '"></script>'
            . '<script>(()=>{const storageKey="lf-theme";const button=document.querySelector("[data-theme-toggle]");if(!button){return;}button.addEventListener("click",()=>{const currentTheme=document.documentElement.getAttribute("data-bs-theme")==="light"?"light":"dark";const nextTheme=currentTheme==="dark"?"light":"dark";document.documentElement.setAttribute("data-bs-theme",nextTheme);window.localStorage.setItem(storageKey,nextTheme);});})();</script>'
            . '<script>window.ui=SwaggerUIBundle({url:' . $openApiJson . ',dom_id:"#swagger-ui",deepLinking:true,presets:[SwaggerUIBundle.presets.apis,SwaggerUIStandalonePreset],layout:"BaseLayout"});</script>'
            . '</body></html>';

        return $this->psr17
            ->createResponse(200)
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withBody($this->psr17->createStream($html));
    }

    private function apiPrefix(): string
    {
        return $this->config->prefix;
    }

    private function joinPath(string $prefix, string $route): string
    {
        $prefix = '/' . trim($prefix, '/');
        $route = '/' . trim($route, '/');

        $prefix = $prefix === '/' ? '' : rtrim($prefix, '/');
        $route = $route === '/' ? '/' : rtrim($route, '/');

        return $prefix . $route;
    }

    private function swaggerUiAssetUrl(string $file): string
    {
        return sprintf(
            '%s/%s/%s',
            self::SWAGGER_ASSET_BASE_URL,
            self::SWAGGER_UI_VERSION,
            $file,
        );
    }
}
