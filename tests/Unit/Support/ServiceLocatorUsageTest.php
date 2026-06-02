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
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src));

        foreach ($files as $file) {
            if (!$file instanceof SplFileInfo || $file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

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

    private function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
