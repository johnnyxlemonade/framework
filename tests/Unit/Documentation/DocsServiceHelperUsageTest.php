<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

final class DocsServiceHelperUsageTest extends TestCase
{
    public function testServiceHelperInDocsIsOnlyPresentedAsRemovedApi(): void
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
                !str_contains($contents, 'Removed `service()` helper')
                && !str_contains($contents, 'Global service-backed helpers were removed')
                || str_contains($contents, 'Compatibility `service()` helper')
                || str_contains($contents, 'compatibility API')
            ) {
                $violations[] = basename($file);
            }
        }

        self::assertSame([], $violations);
    }

    public function testDocsDoNotPresentRemovedGlobalHelpersAsRecommendedApi(): void
    {
        $docsDirectory = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'docs';
        $files = glob($docsDirectory . DIRECTORY_SEPARATOR . '*.md');

        self::assertIsArray($files);

        $forbiddenPhrases = [
            'remain available as compatibility API',
            'Compatibility `service()` helper',
            'delegates to the current framework container',
            'returns the provided default value',
        ];

        $violations = [];
        foreach ($files as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents);

            foreach ($forbiddenPhrases as $phrase) {
                if (str_contains($contents, $phrase)) {
                    $violations[] = basename($file) . ' contains "' . $phrase . '"';
                }
            }
        }

        self::assertSame([], $violations);
    }
}
