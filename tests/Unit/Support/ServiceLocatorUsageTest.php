<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class ServiceLocatorUsageTest extends TestCase
{
    public function testServiceLocatorContainerIsOnlyUsedByServiceHelperInSource(): void
    {
        $root = dirname(__DIR__, 3);
        $src = $root . DIRECTORY_SEPARATOR . 'src';
        $allowed = $this->normalizePath($src . DIRECTORY_SEPARATOR . 'Support/Helpers/service.php');

        $violations = [];
        foreach ($this->phpFiles($src) as $file) {
            $path = $this->normalizePath($file->getPathname());
            $contents = file_get_contents($file->getPathname());
            if ($contents === false || !str_contains($contents, 'ServiceLocator::container(')) {
                continue;
            }

            if ($path !== $allowed) {
                $violations[] = $path;
            }
        }

        self::assertSame([], $violations);
    }

    public function testServiceLocatorSetContainerIsOnlyUsedByFrameworkCompatibilityBootstrap(): void
    {
        $root = dirname(__DIR__, 3);
        $src = $root . DIRECTORY_SEPARATOR . 'src';
        $allowed = $this->normalizePath($src . DIRECTORY_SEPARATOR . 'Core/Framework.php');

        $violations = [];
        foreach ($this->phpFiles($src) as $file) {
            $path = $this->normalizePath($file->getPathname());
            $contents = file_get_contents($file->getPathname());
            if ($contents === false || !str_contains($contents, 'ServiceLocator::setContainer(')) {
                continue;
            }

            if ($path !== $allowed || !str_contains($contents, 'bootCompatibilityHelperRuntime')) {
                $violations[] = $path;
            }
        }

        self::assertSame([], $violations);
    }

    public function testGlobalServiceHelperCallsInSourceAreOnlyInHelperCompatibilityLayer(): void
    {
        $root = dirname(__DIR__, 3);
        $src = $root . DIRECTORY_SEPARATOR . 'src';
        $allowedPrefix = $this->normalizePath($src . DIRECTORY_SEPARATOR . 'Support/Helpers/');

        $violations = [];
        foreach ($this->phpFiles($src) as $file) {
            $path = $this->normalizePath($file->getPathname());
            $contents = file_get_contents($file->getPathname());
            if ($contents === false || !$this->containsGlobalServiceHelperCall($contents)) {
                continue;
            }

            if (!str_starts_with($path, $allowedPrefix)) {
                $violations[] = $path;
            }
        }

        self::assertSame([], $violations);
    }

    public function testCoreControllerValidationAndViewDoNotUseServiceLocator(): void
    {
        $root = dirname(__DIR__, 3);
        $src = $root . DIRECTORY_SEPARATOR . 'src';
        $directories = [
            $src . DIRECTORY_SEPARATOR . 'Core/Controller',
            $src . DIRECTORY_SEPARATOR . 'Validation',
            $src . DIRECTORY_SEPARATOR . 'View',
        ];

        $violations = [];
        foreach ($directories as $directory) {
            foreach ($this->phpFiles($directory) as $file) {
                $contents = file_get_contents($file->getPathname());
                if ($contents !== false && str_contains($contents, 'ServiceLocator')) {
                    $violations[] = $this->normalizePath($file->getPathname());
                }
            }
        }

        self::assertSame([], $violations);
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function phpFiles(string $directory): iterable
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($files as $file) {
            if (!$file instanceof SplFileInfo || $file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

            yield $file;
        }
    }

    private function containsGlobalServiceHelperCall(string $contents): bool
    {
        $tokens = token_get_all($contents);

        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_STRING || strtolower($token[1]) !== 'service') {
                continue;
            }

            $next = $this->nextSignificantToken($tokens, $index);
            if ($next !== '(') {
                continue;
            }

            $previous = $this->previousSignificantToken($tokens, $index);
            if (in_array($previous, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION], true)) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * @param array<int, array{0: int, 1: string, 2?: int}|string> $tokens
     */
    private function previousSignificantToken(array $tokens, int $index): int|string|null
    {
        for ($i = $index - 1; $i >= 0; $i--) {
            $token = $tokens[$i];
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return is_array($token) ? $token[0] : $token;
        }

        return null;
    }

    /**
     * @param array<int, array{0: int, 1: string, 2?: int}|string> $tokens
     */
    private function nextSignificantToken(array $tokens, int $index): int|string|null
    {
        $count = count($tokens);
        for ($i = $index + 1; $i < $count; $i++) {
            $token = $tokens[$i];
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return is_array($token) ? $token[0] : $token;
        }

        return null;
    }

    private function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
