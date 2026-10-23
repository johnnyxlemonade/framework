<?php

declare(strict_types=1);

namespace Lemonade\Framework\View;

use Lemonade\Framework\Image\ImageViewOptions;
use Lemonade\Framework\Image\Value\ImageReference;
use LogicException;
use RuntimeException;

/**
 * Renders PHP templates while keeping layout state and request helpers on one isolated view instance.
 */
final class View
{
    /**
     * @var array<string, mixed>
     */
    private array $shared = [];
    /**
     * @var array<string, mixed>
     */
    private array $requestShared = [];
    /**
     * @var array<string, mixed>
     */
    private array $temporaryShared = [];
    /**
     * @var array<string, string>
     */
    private array $sections = [];
    /**
     * @var list<string>
     */
    private array $sectionStack = [];
    private int $renderDepth = 0;
    private ?string $extends = null;
    private ?string $content = null;
    private ?ViewHelpers $helpers = null;

    /**
     * Creates an unbound view prototype for the configured template root.
     */
    public function __construct(
        private readonly string $basePath = 'app/views',
        private readonly ?ViewResourceRegistry $resources = null,
    ) {
    }

    /**
     * Resets request bindings, temporary data, and in-progress rendering state on every clone.
     */
    public function __clone(): void
    {
        $this->helpers = null;
        $this->requestShared = [];
        $this->temporaryShared = [];
        $this->renderDepth = 0;
        $this->resetViewState();
    }

    /**
     * Creates an isolated request-local clone with explicit helpers and request-scoped template data.
     *
     * @param array<string, mixed> $shared
     *
     * @internal The framework renderer lifecycle owns request binding.
     */
    public function forRequest(ViewHelpers $helpers, array $shared = []): self
    {
        $view = clone $this;
        $view->helpers = $helpers;
        $view->requestShared = array_merge($shared, ['helpers' => $helpers]);

        return $view;
    }

    /**
     * Shares a value with this view and all clones created from it.
     */
    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /**
     * Shares a value only until the current or next top-level render finishes.
     */
    public function shareOnce(string $key, mixed $value): void
    {
        $this->temporaryShared[$key] = $value;
    }

    /**
     * Shares multiple values with this view and all clones created from it.
     *
     * @param array<string, mixed> $data
     */
    public function shares(array $data): void
    {
        $this->shared = array_merge($this->shared, $data);
    }

    /**
     * Renders a view and its optional layout using one isolated render state.
     *
     * @param array<string, mixed> $data
     */
    public function render(string $view, array $data = []): string
    {
        $topLevelRender = $this->renderDepth === 0;
        $this->renderDepth++;

        try {
            if ($topLevelRender) {
                $this->resetViewState();
            }

            $viewData = $this->viewData($data);
            $content = $this->renderFile($view, $viewData);
            if ($this->extends !== null) {
                $this->content = $content;
                return $this->renderFile($this->extends, $viewData);
            }

            return $content;
        } finally {
            $this->renderDepth--;
            if ($topLevelRender) {
                $this->temporaryShared = [];
            }
        }
    }

    /**
     * Renders a nested view with the current request helpers and shared data.
     *
     * @param array<string, mixed> $data
     */
    public function partial(string $view, array $data = []): string
    {
        $topLevelRender = $this->renderDepth === 0;
        $this->renderDepth++;

        try {
            return $this->renderFile($view, $this->viewData($data));
        } finally {
            $this->renderDepth--;
            if ($topLevelRender) {
                $this->temporaryShared = [];
            }
        }
    }

    /**
     * Renders a content view inside a layout using one request and render context.
     *
     * @param array<string, mixed> $data
     */
    public function template(string $layoutView, string $contentView, array $data = []): string
    {
        $topLevelRender = $this->renderDepth === 0;
        $this->renderDepth++;

        try {
            if ($topLevelRender) {
                $this->resetViewState();
            }

            $viewData = $this->viewData($data);
            $content = $this->renderFile($contentView, $viewData);
            $this->content = $content;
            return $this->renderFile($layoutView, $viewData);
        } finally {
            $this->renderDepth--;
            if ($topLevelRender) {
                $this->temporaryShared = [];
            }
        }
    }

    /**
     * Selects the layout rendered after the current top-level view completes.
     */
    public function extend(string $layoutView): void
    {
        $this->extends = $layoutView;
    }

    /**
     * Returns the rendered child content while a layout is being rendered.
     */
    public function content(): string
    {
        return $this->content ?? '';
    }

    /**
     * Starts capturing a named layout section.
     */
    public function start(string $name): void
    {
        $this->sectionStack[] = $name;
        ob_start();
    }

    /**
     * Finishes the most recently started section and stores its captured content.
     */
    public function end(): void
    {
        $name = array_pop($this->sectionStack);
        if ($name === null) {
            throw new RuntimeException('View section end() called without start().');
        }

        $this->sections[$name] = (string) ob_get_clean();
    }

    /**
     * Returns a captured section or the caller-provided default.
     */
    public function section(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    /**
     * Translates one message with scalar replacements in the current request context.
     *
     * @param array<string, scalar|null> $replacements
     */
    public function lang(string $key, array $replacements = [], ?string $locale = null): string
    {
        return $this->requestHelpers()->lang($key, $replacements, $locale);
    }

    /**
     * Returns one complete translation group for a locale in the current request context.
     *
     * @return array<string, string>
     */
    public function langGroup(string $group, ?string $locale = null): array
    {
        return $this->requestHelpers()->langGroup($group, $locale);
    }

    /**
     * Returns every translation group for a locale in the current request context.
     *
     * @return array<string, array<string, string>>
     */
    public function langAll(?string $locale = null): array
    {
        return $this->requestHelpers()->langAll($locale);
    }

    /**
     * Returns the active runtime locale or a configured or caller-provided fallback.
     */
    public function currentLocale(string $default = 'en'): string
    {
        return $this->requestHelpers()->currentLocale($default);
    }

    /**
     * Generates a URL for a named route.
     *
     * @param array<string, scalar|null> $params
     */
    public function url(string $route, array $params = []): string
    {
        return $this->requestHelpers()->url($route, $params);
    }

    /**
     * Generates a locale-aware URL for a named route.
     *
     * @param array<string, scalar|null> $params
     */
    public function localizedUrl(string $route, array $params = [], ?string $locale = null): string
    {
        return $this->requestHelpers()->localizedUrl($route, $params, $locale);
    }

    /**
     * Returns a root-relative asset URL without requiring a canonical application origin.
     */
    public function asset(string $path): string
    {
        return $this->requestHelpers()->asset($path);
    }

    /**
     * Renders an image reference through the configured image integration.
     *
     * @throws LogicException When image services or request helpers are not configured.
     */
    public function image(?ImageReference $reference, ?ImageViewOptions $options = null): string
    {
        return $this->requestHelpers()->image($reference, $options);
    }

    /**
     * Renders an escaped hidden field containing a token from the current request session.
     */
    public function csrfField(string $name = 'default'): string
    {
        return $this->requestHelpers()->csrfField($name);
    }

    /**
     * Returns a named CSRF token from the current request session.
     */
    public function csrfToken(string $name = 'default'): string
    {
        return $this->requestHelpers()->csrfToken($name);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderFile(string $view, array $data): string
    {
        $file = $this->resolveFile($view);
        if (!is_file($file)) {
            throw new RuntimeException(sprintf('View not found: %s', $file));
        }

        $bufferLevel = ob_get_level();
        ob_start();

        try {
            extract($data, EXTR_SKIP);
            include $file;
            return (string) ob_get_clean();
        } catch (\Throwable $exception) {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }

            throw $exception;
        }
    }

    private function resolveFile(string $view): string
    {
        $this->resources?->freeze();

        if (!str_contains($view, '::')) {
            return rtrim($this->basePath, '/\\') . DIRECTORY_SEPARATOR . str_replace('.', DIRECTORY_SEPARATOR, $view) . '.php';
        }

        if ($this->resources === null) {
            throw new RuntimeException('Namespaced views require a ViewResourceRegistry.');
        }

        [$namespace, $name] = explode('::', $view, 2);

        return $this->resources->resolve($namespace, $name);
    }

    private function resetViewState(): void
    {
        $this->sections = [];
        $this->sectionStack = [];
        $this->extends = null;
        $this->content = null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function viewData(array $data): array
    {
        return array_merge($this->shared, $this->requestShared, $this->temporaryShared, $data);
    }

    private function requestHelpers(): ViewHelpers
    {
        if ($this->helpers === null) {
            throw new LogicException(
                'View helpers are not bound. Render through the request-scoped ViewRendererInterface.',
            );
        }

        return $this->helpers;
    }
}
