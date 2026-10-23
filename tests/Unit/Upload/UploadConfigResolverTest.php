<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Upload;

use InvalidArgumentException;
use Lemonade\Framework\Mime\MimeTypeCatalog;
use Lemonade\Framework\Mime\MimeRisk;
use Lemonade\Framework\Mime\MimeType;
use Lemonade\Framework\Mime\MimeTypeDefinition;
use Lemonade\Framework\Upload\Config\UploadConfigDefinition;
use Lemonade\Framework\Upload\Config\UploadConfigResolver;
use Lemonade\Framework\Upload\UploadVisibility;
use PHPUnit\Framework\Attributes\DataProvider;
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

        self::assertSame(10 * 1024 * 1024, $config->files['documents']->maxBytes());
        self::assertSame(['pdf', 'docx'], $config->files['documents']->allowedExtensions());
        self::assertSame(500 * 1024, $config->images['admin-image']->maxBytes());
        self::assertSame(['jpg', 'jpeg'], $config->images['admin-image']->allowedExtensions());
        self::assertSame('documents', $config->files['documents']->targetDirectory());
        self::assertSame(UploadVisibility::Public, $config->files['documents']->visibility());
        self::assertSame([], $config->files['documents']->allowedRisks());
        self::assertSame('images', $config->images['admin-image']->targetDirectory());
        self::assertTrue($config->images['admin-image']->reencode());
        self::assertNull($config->images['admin-image']->minWidth());
        self::assertNull($config->images['admin-image']->maxWidth());
        self::assertNull($config->images['admin-image']->minHeight());
        self::assertNull($config->images['admin-image']->maxHeight());
        self::assertSame(UploadVisibility::Public, $config->images['admin-image']->visibility());
        self::assertSame([], $config->images['admin-image']->allowedRisks());
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

        self::assertTrue($catalog->matches($config->files['documents']->allowedExtensions()[0], 'image/jpeg'));
        self::assertTrue($catalog->matches($config->files['documents']->allowedExtensions()[1], 'application/pdf'));
        self::assertTrue($catalog->matches(
            $config->files['documents']->allowedExtensions()[2],
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ));
        self::assertFalse($catalog->matches($config->files['documents']->allowedExtensions()[2], 'application/pdf'));
    }

    public function testPublicProfileRejectsActiveRiskEvenWhenAllowedRisksTriesToEnableIt(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('public storage cannot authorize risk "active_content"');

        $this->resolve([
            'files' => [
                'public-html' => [
                    'target_directory' => 'pages',
                    'allowed_extensions' => ['html'],
                    'allowed_risks' => ['active_content'],
                ],
            ],
        ]);
    }

    public function testPrivateProfileRequiresExplicitRiskAndResolvesAuthorizedRisk(): void
    {
        try {
            $this->resolve([
                'files' => [
                    'private-html' => [
                        'target_directory' => 'pages',
                        'visibility' => 'private',
                        'allowed_extensions' => ['html'],
                    ],
                ],
            ]);
            self::fail('Private active content must require an explicit risk authorization.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('private storage risk policy', $exception->getMessage());
        }

        $config = $this->resolve([
            'files' => [
                'private-html' => [
                    'target_directory' => 'pages',
                    'visibility' => 'private',
                    'allowed_extensions' => ['html'],
                    'allowed_risks' => ['active_content'],
                ],
            ],
        ]);

        self::assertSame(UploadVisibility::Private, $config->files['private-html']->visibility());
        self::assertSame([MimeRisk::ActiveContent], $config->files['private-html']->allowedRisks());
    }

    public function testUnclassifiedCustomFormatIsFailClosedAndCanOnlyBeExplicitlyAllowedPrivately(): void
    {
        $catalog = MimeTypeCatalog::fromDefaultAndAdditionalDefinitions([
            new MimeTypeDefinition(
                MimeType::fromString('application/vnd.acme.unclassified'),
                ['acme-unclassified'],
                risk: MimeRisk::Unclassified,
            ),
        ]);
        $resolver = new UploadConfigResolver($catalog);

        try {
            $resolver->resolve(UploadConfigDefinition::fromArrayData([
                'files' => [
                    'public-custom' => [
                        'target_directory' => 'custom',
                        'allowed_extensions' => ['acme-unclassified'],
                    ],
                ],
            ]));
            self::fail('An unclassified public format must be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('public storage risk policy', $exception->getMessage());
        }

        $config = $resolver->resolve(UploadConfigDefinition::fromArrayData([
            'files' => [
                'private-custom' => [
                    'target_directory' => 'custom',
                    'visibility' => 'private',
                    'allowed_extensions' => ['acme-unclassified'],
                    'allowed_risks' => ['unclassified'],
                ],
            ],
        ]));

        self::assertSame([MimeRisk::Unclassified], $config->files['private-custom']->allowedRisks());
    }

    #[DataProvider('unsafeTargetDirectoryProvider')]
    public function testRejectsUnsafeTargetDirectories(string $targetDirectory): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid target_directory');

        $this->resolve([
            'files' => [
                'unsafe-path' => [
                    'target_directory' => $targetDirectory,
                    'allowed_extensions' => ['txt'],
                ],
            ],
        ]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsafeTargetDirectoryProvider(): iterable
    {
        yield 'parent traversal' => ['../outside'];
        yield 'embedded traversal' => ['safe/../../outside'];
        yield 'absolute unix path' => ['/var/uploads'];
        yield 'absolute windows path' => ['C:\\uploads'];
        yield 'backslash separator' => ['safe\\nested'];
        yield 'empty segment' => ['safe//nested'];
        yield 'nul byte' => ["safe\0nested"];
    }

    /** @param array<string, mixed> $data */
    private function resolve(array $data): \Lemonade\Framework\Upload\Config\UploadConfig
    {
        return (new UploadConfigResolver())->resolve(
            UploadConfigDefinition::fromArrayData($data),
        );
    }
}
