<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Upload;

use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Mime\MimeType;
use Lemonade\Framework\Mime\MimeTypeCatalog;
use Lemonade\Framework\Mime\MimeTypeDefinition;
use Lemonade\Framework\Mime\MimeTypeDetectorInterface;
use Lemonade\Framework\Mime\MimeRisk;
use Lemonade\Framework\Upload\Exception\UploadValidationException;
use Lemonade\Framework\Upload\FileUploadOptions;
use Lemonade\Framework\Upload\FileUploadValidator;
use Lemonade\Framework\Upload\Staging\StagedUpload;
use Lemonade\Framework\Upload\UploadVisibility;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FileUploadValidatorTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            @unlink($path);
        }

        $this->temporaryFiles = [];
    }

    public function testRejectsZipPayloadNamedAsDocx(): void
    {
        $validator = $this->validator('application/zip');
        $upload = $this->upload('document.docx', "PK\x03\x04zip-payload");
        $options = $this->options(['docx']);

        $this->expectException(UploadValidationException::class);
        $validator->validate($upload, $options);
    }

    public function testAcceptsDocxWhenServerDetectionReturnsTheCanonicalOfficeMime(): void
    {
        $canonicalMime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        $validator = $this->validator($canonicalMime);
        $upload = $this->upload('document.docx', 'office-document-payload');
        $options = $this->options(['docx']);

        $detected = $validator->validate($upload, $options);

        self::assertSame($canonicalMime, $detected->value());
    }

    public function testRejectsPdfPayloadNamedAsDoc(): void
    {
        $validator = $this->validator('application/pdf');
        $upload = $this->upload('document.doc', "%PDF-1.4\n");
        $options = $this->options(['pdf', 'doc']);

        $this->expectException(UploadValidationException::class);
        $validator->validate($upload, $options);
    }

    public function testAcceptsPdfFromServerDetectionDespiteAnUntrustedClientMimeType(): void
    {
        $validator = $this->validator('application/pdf');
        $upload = $this->upload('document.pdf', "%PDF-1.4\n", 'application/octet-stream');
        $options = $this->options(['pdf']);

        $detected = $validator->validate($upload, $options);

        self::assertSame('application/pdf', $detected->value());
    }

    public function testDoesNotTreatOctetStreamAsAMatchForEveryExtension(): void
    {
        $validator = $this->validator('application/octet-stream');
        $upload = $this->upload('document.pdf', 'unclassified payload');
        $options = $this->options(['pdf']);

        $this->expectException(UploadValidationException::class);
        $validator->validate($upload, $options);
    }

    public function testRejectsUnknownExtensionEvenWhenTheProfileListsIt(): void
    {
        $validator = $this->validator('text/plain');
        $upload = $this->upload('document.unknown', 'plain text');
        $options = $this->options(['unknown']);

        $this->expectException(UploadValidationException::class);
        $validator->validate($upload, $options);
    }

    public function testRejectsKnownExecutableExtensionWhenTheProfileDoesNotAllowIt(): void
    {
        $validator = $this->validator('application/vnd.microsoft.portable-executable');
        $upload = $this->upload('binary.exe', 'binary payload');
        $options = $this->options(['pdf']);

        $this->expectException(UploadValidationException::class);
        $validator->validate($upload, $options);
    }

    public function testRejectsSvgWhenTheDefaultImageExtensionPolicyDoesNotAllowIt(): void
    {
        $validator = $this->validator('image/svg+xml');
        $upload = $this->upload('image.svg', '<svg/>');
        $options = $this->options(['jpg', 'jpeg', 'png', 'webp']);

        $this->expectException(UploadValidationException::class);
        $validator->validate($upload, $options);
    }

    #[DataProvider('publicUnsafeFormatProvider')]
    public function testPublicStorageRejectsActiveExecutableAndUnclassifiedFormats(
        string $extension,
        string $mime,
        MimeRisk $risk,
    ): void {
        $catalog = MimeTypeCatalog::default();

        if ($risk === MimeRisk::Unclassified) {
            $catalog = MimeTypeCatalog::fromDefaultAndAdditionalDefinitions([
                new MimeTypeDefinition(
                    MimeType::fromString($mime),
                    [$extension],
                    risk: MimeRisk::Unclassified,
                ),
            ]);
        }

        $validator = $this->validator($mime, $catalog);
        $upload = $this->upload('payload.' . $extension, 'risk-policy-payload');
        $options = $this->options(
            [$extension],
            UploadVisibility::Public,
            [$risk],
        );

        $this->expectException(UploadValidationException::class);
        $validator->validate($upload, $options);
    }

    /**
     * @return iterable<string, array{string, string, MimeRisk}>
     */
    public static function publicUnsafeFormatProvider(): iterable
    {
        yield 'html' => ['html', 'text/html', MimeRisk::ActiveContent];
        yield 'svg' => ['svg', 'image/svg+xml', MimeRisk::ActiveContent];
        yield 'javascript' => ['js', 'text/javascript', MimeRisk::ActiveContent];
        yield 'php' => ['php', 'application/x-httpd-php', MimeRisk::Executable];
        yield 'phtml' => ['phtml', 'application/x-httpd-php', MimeRisk::Executable];
        yield 'phar' => ['phar', 'application/x-httpd-php-source', MimeRisk::Executable];
        yield 'unclassified' => ['acme-unknown', 'application/vnd.acme.unknown', MimeRisk::Unclassified];
    }

    public function testPrivateStorageRequiresAndHonorsExplicitRiskAuthorization(): void
    {
        $validator = $this->validator('text/html');
        $upload = $this->upload('document.html', '<!doctype html><title>Private</title>');

        $this->expectException(UploadValidationException::class);

        try {
            $validator->validate(
                $upload,
                $this->options(['html'], UploadVisibility::Private),
            );
        } finally {
            $allowed = $validator->validate(
                $upload,
                $this->options(
                    ['html'],
                    UploadVisibility::Private,
                    [MimeRisk::ActiveContent],
                ),
            );

            self::assertSame('text/html', $allowed->value());
        }
    }

    public function testDetectsMimeExactlyOnceForAValidatedGenericUpload(): void
    {
        $detector = new CountingMimeTypeDetector(MimeType::fromString('application/pdf'));
        $validator = $this->validatorWithDetector($detector);
        $upload = $this->upload('document.pdf', "%PDF-1.4\n");
        $options = $this->options(['pdf']);

        $validator->validate($upload, $options);

        self::assertSame(1, $detector->calls);
    }

    public function testCustomCatalogDefinitionDoesNotEnableAnUploadExtension(): void
    {
        $customMime = 'application/vnd.acme.document';
        $catalog = MimeTypeCatalog::fromDefaultAndAdditionalDefinitions([
            new MimeTypeDefinition(
                MimeType::fromString($customMime),
                ['acme'],
                risk: MimeRisk::Passive,
            ),
        ]);
        $validator = $this->validator($customMime, $catalog);
        $upload = $this->upload('document.acme', 'custom document');
        $options = $this->options([]);

        $this->expectException(UploadValidationException::class);
        $validator->validate($upload, $options);
    }

    private function validator(string $mime, ?MimeTypeCatalog $catalog = null): FileUploadValidator
    {
        return $this->validatorWithDetector(
            new StaticMimeTypeDetector(MimeType::fromString($mime)),
            $catalog,
        );
    }

    private function validatorWithDetector(
        MimeTypeDetectorInterface $detector,
        ?MimeTypeCatalog $catalog = null,
    ): FileUploadValidator {
        return new FileUploadValidator(
            new FileUploadValidatorTranslatorStub(),
            $detector,
            $catalog ?? MimeTypeCatalog::default(),
        );
    }

    private function upload(
        string $filename,
        string $contents,
        string $clientMime = 'application/octet-stream',
    ): StagedUpload
    {
        unset($clientMime);

        $path = tempnam(sys_get_temp_dir(), 'lemonade-upload-');

        if ($path === false) {
            self::fail('Temporary upload file cannot be created.');
        }

        file_put_contents($path, $contents);
        $this->temporaryFiles[] = $path;

        return new StagedUpload(
            path: $path,
            clientFilename: $filename,
            sizeBytes: strlen($contents),
            sha256: hash('sha256', $contents),
        );
    }

    /**
     * @param list<string> $extensions
     * @param list<MimeRisk> $allowedRisks
     */
    private function options(
        array $extensions,
        UploadVisibility $visibility = UploadVisibility::Public,
        array $allowedRisks = [],
    ): FileUploadOptions {
        return new FileUploadOptions(
            targetDirectory: 'files',
            targetRelativeDirectory: 'files',
            allowedExtensions: $extensions,
            visibility: $visibility,
            allowedRisks: $allowedRisks,
        );
    }
}

final readonly class StaticMimeTypeDetector implements MimeTypeDetectorInterface
{
    public function __construct(private MimeType $mime)
    {
    }

    public function detect(string $path): MimeType
    {
        unset($path);

        return $this->mime;
    }
}

final class CountingMimeTypeDetector implements MimeTypeDetectorInterface
{
    public int $calls = 0;

    public function __construct(private readonly MimeType $mime)
    {
    }

    public function detect(string $path): MimeType
    {
        unset($path);

        ++$this->calls;

        return $this->mime;
    }
}

final class FileUploadValidatorTranslatorStub implements TranslatorInterface
{
    public function setLocale(?string $locale): self
    {
        unset($locale);

        return $this;
    }

    public function locale(): ?string
    {
        return null;
    }

    public function get(string $key, array $replacements = [], ?string $locale = null): string
    {
        unset($replacements, $locale);

        return $key;
    }

    public function group(string $group, ?string $locale = null): array
    {
        unset($locale);

        return [$group => $group];
    }

    public function all(?string $locale = null): array
    {
        unset($locale);

        return ['messages' => ['ok' => 'ok']];
    }
}
