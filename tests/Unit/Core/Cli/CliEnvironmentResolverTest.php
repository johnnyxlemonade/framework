<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Core\Cli;

use Lemonade\Framework\Core\Cli\CliEnvironmentResolver;
use PHPUnit\Framework\TestCase;

final class CliEnvironmentResolverTest extends TestCase
{
    public function testResolveBasePathUsesWorkingDirectoryWhenItLooksLikeApplicationRoot(): void
    {
        $packageRoot = $this->makeDirectory('lemonade-package-');
        $appRoot = $this->makeDirectory('lemonade-app-');

        mkdir($appRoot . '/app', 0777, true);
        mkdir($appRoot . '/storage', 0777, true);

        try {
            $resolver = new CliEnvironmentResolver();

            self::assertSame(
                $appRoot,
                $resolver->resolveBasePath(
                    packageRoot: $packageRoot,
                    workingDirectory: $appRoot,
                ),
            );
        } finally {
            $this->removeDirectory($appRoot);
            $this->removeDirectory($packageRoot);
        }
    }

    public function testResolveBasePathFallsBackToPackageRootWhenWorkingDirectoryIsNotApplicationRoot(): void
    {
        $packageRoot = $this->makeDirectory('lemonade-package-');
        $workingDirectory = $this->makeDirectory('lemonade-other-');

        try {
            $resolver = new CliEnvironmentResolver();

            self::assertSame(
                $packageRoot,
                $resolver->resolveBasePath(
                    packageRoot: $packageRoot,
                    workingDirectory: $workingDirectory,
                ),
            );
        } finally {
            $this->removeDirectory($workingDirectory);
            $this->removeDirectory($packageRoot);
        }
    }

    public function testResolveBasePathUsesCurrentWorkingDirectoryWhenWorkingDirectoryIsNotProvided(): void
    {
        $originalWorkingDirectory = getcwd();
        $packageRoot = $this->makeDirectory('lemonade-package-');
        $appRoot = $this->makeDirectory('lemonade-app-');

        mkdir($appRoot . '/app', 0777, true);
        mkdir($appRoot . '/storage', 0777, true);

        try {
            self::assertTrue(chdir($appRoot));

            $resolver = new CliEnvironmentResolver();

            self::assertSame(
                $appRoot,
                $resolver->resolveBasePath(packageRoot: $packageRoot),
            );
        } finally {
            if (is_string($originalWorkingDirectory)) {
                chdir($originalWorkingDirectory);
            }

            $this->removeDirectory($appRoot);
            $this->removeDirectory($packageRoot);
        }
    }

    private function makeDirectory(string $prefix): string
    {
        $directory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . $prefix
            . bin2hex(random_bytes(8));

        mkdir($directory, 0777, true);

        return $directory;
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $this->removeDirectory($path);
                continue;
            }

            @unlink($path);
        }

        @rmdir($directory);
    }
}
