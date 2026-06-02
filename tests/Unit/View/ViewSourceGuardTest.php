<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\View;

use PHPUnit\Framework\TestCase;

final class ViewSourceGuardTest extends TestCase
{
    public function testViewSourceDoesNotUseGlobalServiceResolving(): void
    {
        $viewDirectory = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'View';
        $files = glob($viewDirectory . DIRECTORY_SEPARATOR . '*.php');

        self::assertIsArray($files);

        $violations = [];
        foreach ($files as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents);

            if (str_contains($contents, 'service(') || str_contains($contents, 'ServiceLocator')) {
                $violations[] = basename($file);
            }
        }

        self::assertSame([], $violations);
    }
}
