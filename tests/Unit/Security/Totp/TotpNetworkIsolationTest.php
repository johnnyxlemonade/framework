<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Security\Totp;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class TotpNetworkIsolationTest extends TestCase
{
    public function testProductionTotpCodeContainsNoNetworkOrQrProviderCapability(): void
    {
        $directory = dirname(__DIR__, 4) . '/src/Security/Totp';
        $forbiddenFragments = [
            'api.qrserver.com',
            'curl_',
            'file_get_contents(',
            'http://',
            'https://',
            'Downloader',
            'ImageProvider',
        ];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            self::assertIsString($contents);

            foreach ($forbiddenFragments as $fragment) {
                self::assertStringNotContainsString($fragment, $contents, $file->getPathname());
            }
        }
    }
}
