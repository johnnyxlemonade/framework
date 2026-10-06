<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Upload;

use InvalidArgumentException;
use Lemonade\Framework\Mime\MimeTypeCatalog;
use Lemonade\Framework\Upload\Config\UploadConfigDefinition;
use Lemonade\Framework\Upload\Config\UploadConfigResolver;
use PHPUnit\Framework\TestCase;

final class UploadConfigResolverTest extends TestCase
{
    public function testNormalizesKnownExtensionPoliciesAndParsesHumanReadableLimits(): void
    {
        $config = $this->resolve([
            'files' => [
                'documents' => [
                    'target_directory' => 'documents',
                    'max_bytes' => '10 MB',
                    'allowed_extensions' => ['.PDF', 'docx'],
                ],
            ],
            'images' => [
                'admin-image' => [
                    'target_directory' => 'images',
                    'max_bytes' => '500kb',
                    'allowed_extensions' => ['JPG', 'jpeg'],
                    'reencode' => true,
                ],
            ],
        ]);

        self::assertSame(10 * 1024 * 1024, $config->files['documents']->maxBytes);
        self::assertSame(['pdf', 'docx'], $config->files['documents']->allowedExtensions);
        self::assertSame(500 * 1024, $config->images['admin-image']->maxBytes);
        self::assertSame(['jpg', 'jpeg'], $config->images['admin-image']->allowedExtensions);
    }

    public function testRejectsUnknownExtensionsDuringConfigResolution(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Upload profile "admin-file": unknown allowed extension "foo".');

        $this->resolve([
            'files' => [
                'admin-file' => [
                    'target_directory' => 'files',
                    'max_bytes' => '2MB',
                    'allowed_extensions' => ['foo'],
                ],
            ],
        ]);
    }

    public function testUsesCatalogFamiliesForExtensionToMimeMatching(): void
    {
        $catalog = MimeTypeCatalog::default();
        $config = $this->resolve([
            'files' => [
                'documents' => [
                    'target_directory' => 'documents',
                    'max_bytes' => 10485760,
                    'allowed_extensions' => ['jpg', 'pdf', 'docx'],
                ],
            ],
        ]);

        self::assertTrue($catalog->matches($config->files['documents']->allowedExtensions[0], 'image/jpeg'));
        self::assertTrue($catalog->matches($config->files['documents']->allowedExtensions[1], 'application/pdf'));
        self::assertTrue($catalog->matches(
            $config->files['documents']->allowedExtensions[2],
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ));
        self::assertFalse($catalog->matches($config->files['documents']->allowedExtensions[2], 'application/pdf'));
    }

    /** @param array<string, mixed> $data */
    private function resolve(array $data): \Lemonade\Framework\Upload\Config\UploadConfig
    {
        return (new UploadConfigResolver())->resolve(
            UploadConfigDefinition::fromArrayData($data),
        );
    }
}
