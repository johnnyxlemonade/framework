<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Upload;

use Lemonade\Framework\Filesystem\Filesystem;
use Lemonade\Framework\Filesystem\Manager\DirectoryManager;
use Lemonade\Framework\Filesystem\Manager\FileManager;
use Lemonade\Framework\Filesystem\Manager\LockManager;
use Lemonade\Framework\Image\Contract\ImageEncoderInterface;
use Lemonade\Framework\Image\Contract\ImageFileWriterInterface;
use Lemonade\Framework\Image\Contract\ImageProcessorInterface;
use Lemonade\Framework\Mime\MimeType;
use Lemonade\Framework\Mime\MimeTypeCatalog;
use Lemonade\Framework\Upload\Exception\UploadValidationException;
use Lemonade\Framework\Upload\FileUploadValidator;
use Lemonade\Framework\Upload\ImageUploadOptions;
use Lemonade\Framework\Upload\ImageUploadValidator;
use Lemonade\Framework\Upload\Storage\UploadStorage;
use Lemonade\Framework\Upload\UploadService;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use PHPUnit\Framework\TestCase;

final class ImageUploadValidatorTest extends TestCase
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

    public function testRejectsDifferentDetectedAndActualImageMimeTypes(): void
    {
        $detector = new CountingMimeTypeDetector(MimeType::fromString('image/jpeg'));
        $validator = $this->imageValidator($detector);
        $upload = $this->upload('image.jpg', $this->pngBytes());

        $this->expectException(UploadValidationException::class);

        try {
            $validator->validate($upload, $this->options(reencode: false));
        } finally {
            self::assertSame(1, $detector->calls);
        }
    }

    public function testAcceptsMatchingDetectedAndActualImageMimeTypes(): void
    {
        $detector = new CountingMimeTypeDetector(MimeType::fromString('image/png'));
        $validator = $this->imageValidator($detector);
        $upload = $this->upload('image.png', $this->pngBytes());

        $detected = $validator->validate($upload, $this->options());

        self::assertSame('image/png', $detected->value());
        self::assertSame(1, $detector->calls);
    }

    public function testRejectsMimeMismatchBeforeStorageWhenReencodingIsDisabled(): void
    {
        $this->assertMimeMismatchDoesNotMoveTheUpload(false);
    }

    public function testRejectsMimeMismatchBeforeStorageWhenReencodingIsEnabled(): void
    {
        $this->assertMimeMismatchDoesNotMoveTheUpload(true);
    }

    public function testRejectsMismatchedGenericUploadBeforeItsStorageDirectoryIsCreated(): void
    {
        $detector = new CountingMimeTypeDetector(MimeType::fromString('application/pdf'));
        $fileValidator = new FileUploadValidator(
            new ImageUploadValidatorTranslatorStub(),
            $detector,
            MimeTypeCatalog::default(),
        );
        $service = new UploadService(
            $fileValidator,
            new ImageUploadValidator($fileValidator, new ImageUploadValidatorTranslatorStub()),
            $this->storage(),
            $this->createMock(ImageProcessorInterface::class),
            $this->createMock(ImageEncoderInterface::class),
            $this->createMock(ImageFileWriterInterface::class),
        );
        $targetDirectory = sys_get_temp_dir() . '/lemonade-upload-target-' . bin2hex(random_bytes(8));
        $upload = $this->upload('document.doc', "%PDF-1.4\n");

        try {
            $service->uploadFile(
                $upload,
                new \Lemonade\Framework\Upload\FileUploadOptions(
                    targetDirectory: $targetDirectory,
                    targetRelativeDirectory: 'files',
                    allowedExtensions: ['pdf', 'doc'],
                ),
            );
            self::fail('The mismatched extension must be rejected before storage.');
        } catch (UploadValidationException) {
            self::assertDirectoryDoesNotExist($targetDirectory);
            self::assertSame(1, $detector->calls);
        }
    }

    private function assertMimeMismatchDoesNotMoveTheUpload(bool $reencode): void
    {
        $detector = new CountingMimeTypeDetector(MimeType::fromString('image/jpeg'));
        $fileValidator = new FileUploadValidator(
            new ImageUploadValidatorTranslatorStub(),
            $detector,
            MimeTypeCatalog::default(),
        );
        $service = new UploadService(
            $fileValidator,
            new ImageUploadValidator($fileValidator, new ImageUploadValidatorTranslatorStub()),
            $this->storage(),
            $this->createMock(ImageProcessorInterface::class),
            $this->createMock(ImageEncoderInterface::class),
            $this->createMock(ImageFileWriterInterface::class),
        );
        $upload = $this->upload('image.jpg', $this->pngBytes());

        try {
            $service->uploadImage($upload, $this->options($reencode));
            self::fail('The MIME mismatch must be rejected before storage.');
        } catch (UploadValidationException) {
            self::assertSame(0, $upload->moveCalls);
            self::assertSame(1, $detector->calls);
        }
    }

    private function imageValidator(CountingMimeTypeDetector $detector): ImageUploadValidator
    {
        $translator = new ImageUploadValidatorTranslatorStub();
        $fileValidator = new FileUploadValidator(
            $translator,
            $detector,
            MimeTypeCatalog::default(),
        );

        return new ImageUploadValidator($fileValidator, $translator);
    }

    private function options(bool $reencode = true): ImageUploadOptions
    {
        return new ImageUploadOptions(
            targetDirectory: sys_get_temp_dir(),
            targetRelativeDirectory: 'images',
            allowedExtensions: ['jpg', 'jpeg', 'png', 'webp'],
            reencode: $reencode,
        );
    }

    private function upload(string $filename, string $contents): RecordingUploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'lemonade-image-upload-');

        if ($path === false) {
            self::fail('Temporary image upload file cannot be created.');
        }

        file_put_contents($path, $contents);
        $this->temporaryFiles[] = $path;

        return new RecordingUploadedFile($path, $filename, strlen($contents));
    }

    private function pngBytes(): string
    {
        $decoded = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL0jQAAAABJRU5ErkJggg==',
            true,
        );

        if ($decoded === false) {
            throw new \RuntimeException('PNG fixture cannot be decoded.');
        }

        return $decoded;
    }

    private function storage(): UploadStorage
    {
        $directoryManager = new DirectoryManager();
        $filesystem = new Filesystem(
            $directoryManager,
            new FileManager(),
            new LockManager($directoryManager),
        );

        return new UploadStorage(new ImageUploadValidatorTranslatorStub(), $filesystem);
    }
}

final class RecordingUploadedFile implements UploadedFileInterface
{
    public int $moveCalls = 0;

    public function __construct(
        private readonly string $path,
        private readonly string $filename,
        private readonly int $size,
    ) {
    }

    public function getStream(): StreamInterface
    {
        $resource = fopen($this->path, 'rb');

        if ($resource === false) {
            throw new \RuntimeException('Upload fixture cannot be opened.');
        }

        return Stream::create($resource);
    }

    public function moveTo(string $targetPath): void
    {
        ++$this->moveCalls;

        copy($this->path, $targetPath);
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getError(): int
    {
        return UPLOAD_ERR_OK;
    }

    public function getClientFilename(): string
    {
        return $this->filename;
    }

    public function getClientMediaType(): string
    {
        return 'application/octet-stream';
    }
}

final class ImageUploadValidatorTranslatorStub implements \Lemonade\Framework\Localization\TranslatorInterface
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
