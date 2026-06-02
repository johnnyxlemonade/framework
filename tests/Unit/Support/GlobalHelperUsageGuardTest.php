<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class GlobalHelperUsageGuardTest extends TestCase
{
    public function testStaticContainerBridgeConceptsAreRemovedFromSource(): void
    {
        $root = dirname(__DIR__, 3);
        $src = $root . DIRECTORY_SEPARATOR . 'src';

        $violations = [];
        foreach ($this->phpFiles($src) as $file) {
            $contents = file_get_contents($file->getPathname());
            if ($contents === false) {
                continue;
            }

            if (str_contains($contents, 'ServiceLocator')
                || str_contains($contents, 'HelperRuntime')
                || str_contains($contents, 'static container bridge')) {
                $violations[] = $this->normalizePath($file->getPathname());
            }
        }

        self::assertSame([], $violations);
    }

    public function testRemovedGlobalServiceBackedHelpersAreNotCalledInSource(): void
    {
        $root = dirname(__DIR__, 3);
        $src = $root . DIRECTORY_SEPARATOR . 'src';

        $violations = [];
        foreach ($this->phpFiles($src) as $file) {
            $path = $this->normalizePath($file->getPathname());
            $contents = file_get_contents($file->getPathname());
            if ($contents === false) {
                continue;
            }

            foreach ($this->removedGlobalHelperCalls($contents) as $helper) {
                $violations[] = $path . ' uses ' . $helper . '()';
            }
        }

        self::assertSame([], $violations);
    }

    public function testRemovedGlobalServiceBackedHelpersAreNotDefinedInSource(): void
    {
        $root = dirname(__DIR__, 3);
        $src = $root . DIRECTORY_SEPARATOR . 'src';

        $violations = [];
        foreach ($this->phpFiles($src) as $file) {
            $path = $this->normalizePath($file->getPathname());
            $contents = file_get_contents($file->getPathname());
            if ($contents === false) {
                continue;
            }

            foreach ($this->removedGlobalHelperDefinitions($contents) as $helper) {
                $violations[] = $path . ' defines ' . $helper . '()';
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

    /**
     * @return list<string>
     */
    private function removedGlobalHelperNames(): array
    {
        return [
            'service',
            'asset',
            'event',
            'queue',
            'config',
            'csrf_field',
            'csrf_token',
            'flash',
            'lang',
            'current_locale',
            'lang_group',
            'lang_all',
            'old',
            'base_path',
            'app_path',
            'storage_path',
            'url',
            'localized_url',
            'current_path',
            'current_query',
            'current_url',
            'current_full_url',
            'is_url_active',
            'is_route_active',
        ];
    }

    /**
     * @return list<string>
     */
    private function removedGlobalHelperCalls(string $contents): array
    {
        $tokens = token_get_all($contents);
        $removed = array_fill_keys($this->removedGlobalHelperNames(), true);
        $calls = [];

        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_STRING) {
                continue;
            }

            $name = $token[1];
            if (!isset($removed[$name])) {
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

            $calls[] = $name;
        }

        return array_values(array_unique($calls));
    }

    /**
     * @return list<string>
     */
    private function removedGlobalHelperDefinitions(string $contents): array
    {
        $tokens = token_get_all($contents);
        $removed = array_fill_keys($this->removedGlobalHelperNames(), true);
        $definitions = [];

        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_FUNCTION) {
                continue;
            }

            $previous = $this->previousSignificantToken($tokens, $index);
            if (in_array($previous, [T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_ABSTRACT, T_FINAL], true)) {
                continue;
            }

            $name = $this->nextFunctionName($tokens, $index);
            if ($name === null) {
                continue;
            }

            if (isset($removed[$name])) {
                $definitions[] = $name;
            }
        }

        return array_values(array_unique($definitions));
    }

    /**
     * @param array<int, array{0: int, 1: string, 2?: int}|string> $tokens
     */
    private function nextFunctionName(array $tokens, int $index): ?string
    {
        $count = count($tokens);
        for ($i = $index + 1; $i < $count; $i++) {
            $token = $tokens[$i];
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            if (is_array($token) && $token[0] === T_STRING) {
                return $token[1];
            }

            return null;
        }

        return null;
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
