<?php

declare(strict_types=1);

namespace Lemonade\Framework\View;

use Lemonade\Framework\Observability\Benchmark\Benchmark;
use Lemonade\Framework\Support\ServiceLocator;
use RuntimeException;

final class View
{
    /**
     * @var array<string, mixed>
     */
    private array $shared = [];
    /**
     * @var array<string, string>
     */
    private array $sections = [];
    /**
     * @var list<string>
     */
    private array $sectionStack = [];
    private ?string $extends = null;
    private ?string $content = null;

    public function __construct(private readonly string $basePath = 'app/views') {}

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
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
        $this->markBenchmark('view_render_start');
        $this->resetViewState();
        $content = $this->renderFile($view, array_merge($this->shared, $data));
        if ($this->extends !== null) {
            $this->content = $content;
            $result = $this->renderFile($this->extends, array_merge($this->shared, $data));
            $this->markBenchmark('view_render_finished');

            return $result;
        }

        $this->markBenchmark('view_render_finished');
        return $content;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function partial(string $view, array $data = []): string
    {
        return $this->renderFile($view, array_merge($this->shared, $data));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function template(string $layoutView, string $contentView, array $data = []): string
    {
        $this->markBenchmark('view_render_start');
        $this->resetViewState();
        $content = $this->renderFile($contentView, array_merge($this->shared, $data));
        $this->content = $content;
        $result = $this->renderFile($layoutView, array_merge($this->shared, $data));
        $this->markBenchmark('view_render_finished');

        return $result;
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

        extract($data, EXTR_SKIP);
        ob_start();
        include $file;
        return (string) ob_get_clean();
    }

    private function resetViewState(): void
    {
        $this->sections = [];
        $this->sectionStack = [];
        $this->extends = null;
        $this->content = null;
    }

    private function markBenchmark(string $name): void
    {
        $container = ServiceLocator::container();
        if ($container === null || !$container->isBound(Benchmark::class)) {
            return;
        }

        $benchmark = $container->get(Benchmark::class);
        $run = $benchmark->current();
        if ($run === null) {
            return;
        }

        $run->mark($name);
    }
}
