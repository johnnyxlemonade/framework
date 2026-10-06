<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Upload;

use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Mime\MimeType;
use Lemonade\Framework\Mime\MimeTypeCatalog;
use Lemonade\Framework\Mime\MimeTypeDefinition;
use Lemonade\Framework\Mime\MimeTypeDetectorInterface;
use Lemonade\Framework\Upload\Exception\UploadValidationException;
use Lemonade\Framework\Upload\FileUploadOptions;
use Lemonade\Framework\Upload\FileUploadValidator;
use Nyholm\Psr7\UploadedFile;
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

    private function upload(string $filename, string $contents, string $clientMime = 'application/octet-stream'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'lemonade-upload-');

        if ($path === false) {
            self::fail('Temporary upload file cannot be created.');
        }

        file_put_contents($path, $contents);
        $this->temporaryFiles[] = $path;

        return new UploadedFile(
            $path,
            strlen($contents),
            UPLOAD_ERR_OK,
            $filename,
            $clientMime,
        );
    }

    /**
     * @param list<string> $extensions
     */
    private function options(array $extensions): FileUploadOptions
    {
        return new FileUploadOptions(
            targetDirectory: 'files',
            targetRelativeDirectory: 'files',
            allowedExtensions: $extensions,
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
