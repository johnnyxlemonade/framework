<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Context;

final class Path
{
    public function __construct(
        private readonly string $basePath,
    ) {}

    public function base(): string
    {
        return rtrim($this->basePath, '/\\');
    }

    public function resolve(string $path = ''): string
    {
        if ($path === '') {
            return $this->base();
        }

        if ($this->isAbsolute($path)) {
            return $this->normalize($path);
        }

        return $this->base() . DIRECTORY_SEPARATOR . ltrim($this->normalize($path), '/\\');
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
            $path .= DIRECTORY_SEPARATOR . trim($this->normalize($segment), '/\\');
        }

        return $this->resolve($path);
    }

    public function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\\\')
            || preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1;
    }

    private function joinRelative(string $base, string $path): string
    {
        if ($path === '') {
            return $base;
        }

        return $base . DIRECTORY_SEPARATOR . ltrim($this->normalize($path), '/\\');
    }

    private function normalize(string $path): string
    {
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }
}
