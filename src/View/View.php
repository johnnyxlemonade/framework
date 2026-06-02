<?php

declare(strict_types=1);

namespace Lemonade\Framework\View;

use RuntimeException;

final class View
{
    /**
     * @var array<string, mixed>
     */
    private array $shared = [];
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

    public function __construct(private readonly string $basePath = 'app/views') {}

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    public function shareOnce(string $key, mixed $value): void
    {
        $this->temporaryShared[$key] = $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function shares(array $data): void
    {
        $this->shared = array_merge($this->shared, $data);
    }

    /**
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

    public function extend(string $layoutView): void
    {
        $this->extends = $layoutView;
    }

    public function content(): string
    {
        return $this->content ?? '';
    }

    public function start(string $name): void
    {
        $this->sectionStack[] = $name;
        ob_start();
    }

    public function end(): void
    {
        $name = array_pop($this->sectionStack);
        if ($name === null) {
            throw new RuntimeException('View section end() called without start().');
        }

        $this->sections[$name] = (string) ob_get_clean();
    }

    public function section(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderFile(string $view, array $data): string
    {
        $file = rtrim($this->basePath, '/\\') . DIRECTORY_SEPARATOR . str_replace('.', DIRECTORY_SEPARATOR, $view) . '.php';
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
        return array_merge($this->shared, $this->temporaryShared, $data);
    }
}
