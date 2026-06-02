<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

final class DocsServiceHelperUsageTest extends TestCase
{
    public function testServiceHelperInDocsIsOnlyPresentedAsCompatibilityApi(): void
    {
        $docsDirectory = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'docs';
        $files = glob($docsDirectory . DIRECTORY_SEPARATOR . '*.md');

        self::assertIsArray($files);

        $violations = [];
        foreach ($files as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents);

            if (!str_contains($contents, 'service(')) {
                continue;
            }

            if (
                !str_contains($contents, 'Compatibility `service()` helper')
                || !str_contains($contents, 'compatibility API')
            ) {
                $violations[] = basename($file);
            }
        }

        self::assertSame([], $violations);
    }
}
