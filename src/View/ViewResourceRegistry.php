<?php

declare(strict_types=1);

namespace Lemonade\Framework\View;

use InvalidArgumentException;
use LogicException;
use RuntimeException;

/**
 * Bootstrap-time registry for provider-owned, namespaced PHP view resources.
 */
final class ViewResourceRegistry
{
    /**
     * @var array<string, string>
     */
    private array $roots = [];
    private bool $frozen = false;

    public function register(string $namespace, string $root): void
    {
        if ($this->frozen) {
            throw new LogicException('View resources can only be registered before view resolution begins.');
        }

        $this->assertValidNamespace($namespace);

        $resolvedRoot = realpath($root);
        if ($resolvedRoot === false || !is_dir($resolvedRoot)) {
            throw new InvalidArgumentException(sprintf('View resource root does not exist: %s', $root));
        }

        if (array_key_exists($namespace, $this->roots)) {
            throw new LogicException(sprintf('View namespace is already registered: %s', $namespace));
        }

        $this->roots[$namespace] = $this->normalizeRoot($resolvedRoot);
    }

    public function freeze(): void
    {
        $this->frozen = true;
    }

    public function resolve(string $namespace, string $view): string
    {
        $this->freeze();
        $this->assertValidNamespace($namespace);

        $root = $this->roots[$namespace] ?? null;
        if ($root === null) {
            throw new RuntimeException(sprintf('View namespace is not registered: %s', $namespace));
        }

        if (preg_match('/\A[a-zA-Z0-9_-]+(?:\.[a-zA-Z0-9_-]+)*\z/D', $view) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid namespaced view name: %s', $view));
        }

        $candidate = $root . DIRECTORY_SEPARATOR . str_replace('.', DIRECTORY_SEPARATOR, $view) . '.php';
        $resolvedFile = realpath($candidate);
        if ($resolvedFile === false || !is_file($resolvedFile)) {
            throw new RuntimeException(sprintf('View not found: %s::%s', $namespace, $view));
        }

        $rootPrefix = $this->rootPrefix($root);
        if (!str_starts_with($resolvedFile, $rootPrefix)) {
            throw new RuntimeException(sprintf('Resolved view escapes its registered root: %s::%s', $namespace, $view));
        }

        return $resolvedFile;
    }

    private function assertValidNamespace(string $namespace): void
    {
        if (preg_match('/\A[a-z][a-z0-9-]*\z/D', $namespace) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid view namespace: %s', $namespace));
        }
    }

    private function normalizeRoot(string $root): string
    {
        if ($root === DIRECTORY_SEPARATOR || preg_match('#\A[A-Za-z]:[\\\\/]\z#D', $root) === 1) {
            return $root;
        }

        return rtrim($root, '/\\');
    }

    private function rootPrefix(string $root): string
    {
        if ($root === DIRECTORY_SEPARATOR || preg_match('#\A[A-Za-z]:[\\\\/]\z#D', $root) === 1) {
            return $root;
        }

        return $root . DIRECTORY_SEPARATOR;
    }
}
