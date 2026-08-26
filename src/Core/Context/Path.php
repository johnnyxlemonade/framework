<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Context;

final class Path
{
    public function __construct(
        private readonly string $basePath,
        private readonly ?string $publicPath = null,
    ) {}

    public function base(): string
    {
        return rtrim($this->normalize($this->basePath, $this->baseSeparator()), '/\\');
    }

    public function resolve(string $path = ''): string
    {
        if ($path === '') {
            return $this->base();
        }

        if ($this->isAbsolute($path)) {
            return $this->normalize($path, $this->separatorFor($path));
        }

        $separator = $this->baseSeparator();

        return $this->base() . $separator . ltrim($this->normalize($path, $separator), '/\\');
    }

    public function publicPath(string $path = ''): string
    {
        return $this->resolveFromBase(
            $this->publicBase(),
            $path,
        );
    }

    public function app(string $path = ''): string
    {
        return $this->resolve($this->joinRelative('app', $path));
    }

    public function config(string $path = ''): string
    {
        return $this->app($this->joinRelative('Config', $path));
    }

    public function storage(string $path = ''): string
    {
        return $this->resolve($this->joinRelative('storage', $path));
    }

    public function join(string ...$segments): string
    {
        $segments = array_values(array_filter(
            $segments,
            static fn(string $segment): bool => $segment !== '',
        ));

        if ($segments === []) {
            return $this->base();
        }

        $path = array_shift($segments);

        foreach ($segments as $segment) {
            $path .= $this->baseSeparator() . trim($this->normalize($segment, $this->baseSeparator()), '/\\');
        }

        return $this->resolve($path);
    }

    public function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\\\')
            || preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1;
    }

    private function publicBase(): string
    {
        if ($this->publicPath !== null && trim($this->publicPath) !== '') {
            return rtrim($this->normalize($this->publicPath, $this->separatorFor($this->publicPath)), '/\\');
        }

        $conventional = $this->base() . $this->baseSeparator() . 'public';
        if (is_dir($conventional)) {
            return $conventional;
        }

        return $this->base();
    }

    private function resolveFromBase(string $base, string $path = ''): string
    {
        $separator = $this->separatorFor($base);
        $base = rtrim($this->normalize($base, $separator), '/\\');

        if ($path === '') {
            return $base;
        }

        if ($this->isAbsolute($path)) {
            return $this->normalize($path, $this->separatorFor($path));
        }

        return $base . $separator . ltrim($this->normalize($path, $separator), '/\\');
    }

    private function joinRelative(string $base, string $path): string
    {
        if ($path === '') {
            return $base;
        }

        return $base . $this->baseSeparator() . ltrim($this->normalize($path, $this->baseSeparator()), '/\\');
    }

    private function normalize(string $path, ?string $separator = null): string
    {
        $separator ??= $this->separatorFor($path);

        return str_replace(['/', '\\'], $separator, $path);
    }

    private function baseSeparator(): string
    {
        return $this->separatorFor($this->basePath);
    }

    private function separatorFor(string $path): string
    {
        if (str_contains($path, '\\') || str_starts_with($path, '\\\\') || preg_match('/^[A-Za-z]:/', $path) === 1) {
            return '\\';
        }

        return '/';
    }
}
