<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class ServiceLocatorUsageTest extends TestCase
{
    public function testServiceLocatorAndHelperRuntimeAreRemovedFromSource(): void
    {
        $root = dirname(__DIR__, 3);
        $src = $root . DIRECTORY_SEPARATOR . 'src';

        $violations = [];
        foreach ($this->phpFiles($src) as $file) {
            $contents = file_get_contents($file->getPathname());
            if ($contents === false) {
                continue;
            }

            if (str_contains($contents, 'ServiceLocator') || str_contains($contents, 'HelperRuntime')) {
                $violations[] = $this->normalizePath($file->getPathname());
            }
        }

        self::assertSame([], $violations);
    }

    public function testServiceLocatorStaticCallsAreRemovedFromSource(): void
    {
        $root = dirname(__DIR__, 3);
        $src = $root . DIRECTORY_SEPARATOR . 'src';

        $violations = [];
        foreach ($this->phpFiles($src) as $file) {
            $contents = file_get_contents($file->getPathname());
            if ($contents === false) {
                continue;
            }

            if (!str_contains($contents, 'ServiceLocator::container(')
                && !str_contains($contents, 'ServiceLocator::setContainer(')) {
                continue;
            }

            $violations[] = $this->normalizePath($file->getPathname());
        }

        self::assertSame([], $violations);
    }

    public function testGlobalServiceHelperIsNotCalledInSource(): void
    {
        $root = dirname(__DIR__, 3);
        $src = $root . DIRECTORY_SEPARATOR . 'src';
        $serviceHelper = $this->normalizePath($src . DIRECTORY_SEPARATOR . 'Support/Helpers/service.php');

        $violations = [];
        foreach ($this->phpFiles($src) as $file) {
            $path = $this->normalizePath($file->getPathname());
            $contents = file_get_contents($file->getPathname());
            if ($contents === false || !$this->containsGlobalServiceHelperCall($contents)) {
                continue;
            }

            if ($path !== $serviceHelper) {
                $violations[] = $path;
            }
        }

        self::assertSame([], $violations);
    }

    public function testCoreControllerValidationAndViewDoNotUseStaticHelperRuntime(): void
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
                if ($contents !== false
                    && (str_contains($contents, 'ServiceLocator') || str_contains($contents, 'HelperRuntime'))) {
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
