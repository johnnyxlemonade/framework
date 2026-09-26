<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Migration;

use FilesystemIterator;
use Lemonade\Framework\Database\Migration\Exception\MigrationDiscoveryException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Registers migration classes from one explicitly configured PSR-4 directory.
 *
 * Discovery is recursive only below the supplied directory. It does not scan
 * application roots, Composer metadata, or any implicit filesystem location.
 */
final class MigrationDirectoryRegistrar
{
    /**
     * @throws MigrationDiscoveryException When the explicit directory, namespace, or class mapping is invalid.
     */
    public function registerDirectory(MigrationRegistry $registry, string $directory, string $namespace): void
    {
        $root = $this->resolveDirectory($directory);
        $namespace = $this->normalizeNamespace($namespace);
        $files = $this->phpFiles($root);

        foreach ($files as $file) {
            $migrationClass = $this->classNameForFile($root, $namespace, $file);

            if (!$this->declaresClass($file, $migrationClass)) {
                throw new MigrationDiscoveryException(sprintf(
                    'Migration file "%s" does not declare expected class "%s".',
                    $file,
                    $migrationClass,
                ));
            }

            if (!class_exists($migrationClass)) {
                throw new MigrationDiscoveryException(sprintf(
                    'Migration class "%s" from file "%s" could not be autoloaded.',
                    $migrationClass,
                    $file,
                ));
            }

            if (!is_subclass_of($migrationClass, MigrationInterface::class)) {
                throw new MigrationDiscoveryException(sprintf(
                    'Migration class "%s" must implement %s.',
                    $migrationClass,
                    MigrationInterface::class,
                ));
            }

            $registry->register($migrationClass);
        }
    }

    private function resolveDirectory(string $directory): string
    {
        if (!file_exists($directory)) {
            throw new MigrationDiscoveryException(sprintf('Migration directory "%s" does not exist.', $directory));
        }

        if (!is_dir($directory)) {
            throw new MigrationDiscoveryException(sprintf('Migration path "%s" is not a directory.', $directory));
        }

        $root = realpath($directory);
        if ($root === false) {
            throw new MigrationDiscoveryException(sprintf('Migration directory "%s" could not be resolved.', $directory));
        }

        return $root === DIRECTORY_SEPARATOR ? $root : rtrim($root, DIRECTORY_SEPARATOR);
    }

    private function normalizeNamespace(string $namespace): string
    {
        $namespace = trim($namespace, " \t\r\n\0\x0B\\");
        if ($namespace === '') {
            throw new MigrationDiscoveryException('Migration namespace cannot be empty.');
        }

        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*$/', $namespace) !== 1) {
            throw new MigrationDiscoveryException(sprintf('Migration namespace "%s" is not a valid namespace prefix.', $namespace));
        }

        return $namespace;
    }

    /** @return list<string> */
    private function phpFiles(string $root): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo) {
                continue;
            }

            if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }

            $files[] = $file->getPathname();
        }

        sort($files, SORT_STRING);

        return $files;
    }

    private function classNameForFile(string $root, string $namespace, string $file): string
    {
        $prefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $relative = substr($file, strlen($prefix));
        $classPath = substr($relative, 0, -strlen('.php'));
        $classPath = str_replace(['/', '\\'], '\\', $classPath);

        return $namespace . '\\' . $classPath;
    }

    private function declaresClass(string $file, string $expectedClass): bool
    {
        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new MigrationDiscoveryException(sprintf('Migration file "%s" could not be read.', $file));
        }

        $namespace = '';
        $tokens = token_get_all($contents);
        $count = count($tokens);

        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];
            if (!is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                [$namespace, $index] = $this->namespaceAt($tokens, $index);
                continue;
            }

            if ($token[0] !== T_CLASS) {
                continue;
            }

            $className = $this->classNameAt($tokens, $index);
            if ($className === null) {
                continue;
            }

            $declaredClass = $namespace === '' ? $className : $namespace . '\\' . $className;
            if ($declaredClass === $expectedClass) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{int, string, int}|string> $tokens
     * @return array{string, int}
     */
    private function namespaceAt(array $tokens, int $index): array
    {
        $namespace = '';
        $count = count($tokens);

        for ($index++; $index < $count; $index++) {
            $token = $tokens[$index];
            if ($token === ';' || $token === '{') {
                return [trim($namespace, '\\'), $index];
            }

            if (!is_array($token)) {
                continue;
            }

            if (in_array($token[0], [T_STRING, T_NS_SEPARATOR, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE], true)) {
                $namespace .= $token[1];
            }
        }

        return [trim($namespace, '\\'), $index];
    }

    /** @param list<array{int, string, int}|string> $tokens */
    private function classNameAt(array $tokens, int $index): ?string
    {
        $count = count($tokens);

        for ($index++; $index < $count; $index++) {
            $token = $tokens[$index];
            if (is_array($token) && $token[0] === T_WHITESPACE) {
                continue;
            }

            return is_array($token) && $token[0] === T_STRING ? $token[1] : null;
        }

        return null;
    }
}
