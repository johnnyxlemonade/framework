<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Upload;

use Lemonade\Framework\Filesystem\Filesystem;
use Lemonade\Framework\Filesystem\Manager\DirectoryManager;
use Lemonade\Framework\Filesystem\Manager\FileManager;
use Lemonade\Framework\Filesystem\Manager\LockManager;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Image\Contract\ImageEncoderInterface;
use Lemonade\Framework\Image\Gd\GdCapabilities;
use Lemonade\Framework\Image\Gd\GdImageEncoder;
use Lemonade\Framework\Image\Gd\GdImageProcessor;
use Lemonade\Framework\Mime\MimeType;
use Lemonade\Framework\Mime\MimeTypeCatalog;
use Lemonade\Framework\Mime\MimeTypeDetector;
use Lemonade\Framework\Upload\Exception\UploadValidationException;
use Lemonade\Framework\Upload\FileUploadValidator;
use Lemonade\Framework\Upload\ImageUploadOptions;
use Lemonade\Framework\Upload\ImageUploadValidator;
use Lemonade\Framework\Upload\Staging\StagedUpload;
use Lemonade\Framework\Upload\Staging\UploadStaging;
use Lemonade\Framework\Upload\Storage\UploadStorage;
use Lemonade\Framework\Upload\UploadPathPolicy;
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
    private string $basePath = '';

    protected function setUp(): void
    {
        $this->basePath = sys_get_temp_dir() . '/lemonade-image-upload-' . bin2hex(random_bytes(8));
        mkdir($this->basePath . '/public', 0700, true);
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            @unlink($path);
        }

        $this->temporaryFiles = [];

        if (is_dir($this->basePath)) {
            (new DirectoryManager())->delete($this->basePath);
        }
    }

    public function testRejectsDifferentDetectedAndActualImageMimeTypes(): void
    {
        $detector = new CountingMimeTypeDetector(MimeType::fromString('image/jpeg'));
        $validator = $this->imageValidator($detector);
        $upload = $this->snapshot('image.jpg', $this->pngBytes());

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
        $upload = $this->snapshot('image.png', $this->pngBytes());

        $detected = $validator->validate($upload, $this->options());

        self::assertSame('image/png', $detected->mimeType()->value());
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

    public function testTruncatedPngThatPassesMetadataInspectionIsNeverPublished(): void
    {
        $contents = $this->truncatedPngBytes();
        $metadataPath = $this->basePath . '/truncated.png';
        file_put_contents($metadataPath, $contents);
        self::assertIsArray(getimagesize($metadataPath));
        $upload = $this->upload('truncated.png', $contents);

        $this->expectException(UploadValidationException::class);

        try {
            $this->service()->uploadImage($upload, $this->options(reencode: false));
        } finally {
            self::assertDirectoryDoesNotExist($this->basePath . '/public/uploads/images');
            self::assertSame([], $this->stagedFiles());
        }
    }

    public function testReencodeFalsePublishesOnlyAfterCompleteDecodeAndPreservesValidatedBytes(): void
    {
        $contents = $this->pngBytes();
        $uploaded = $this->service()->uploadImage(
            $this->upload('image.png', $contents),
            $this->options(reencode: false),
        );

        self::assertSame($contents, file_get_contents($uploaded->storedPath()));
        self::assertSame([], $this->stagedFiles());
    }

    public function testReencodeTruePublishesNewImageWithoutTrailingPayload(): void
    {
        $trailingPayload = '<?php echo "trailing";';
        $contents = $this->pngBytes() . $trailingPayload;
        $uploaded = $this->service()->uploadImage(
            $this->upload('image.png', $contents),
            $this->options(reencode: true),
        );
        $published = file_get_contents($uploaded->storedPath());

        self::assertIsString($published);
        self::assertStringNotContainsString($trailingPayload, $published);
        $decoded = imagecreatefrompng($uploaded->storedPath());
        self::assertInstanceOf(\GdImage::class, $decoded);
        unset($decoded);
        self::assertSame([], $this->stagedFiles());
    }

    public function testRejectsMismatchedGenericUploadBeforeItsStorageDirectoryIsCreated(): void
    {
        $detector = new CountingMimeTypeDetector(MimeType::fromString('application/pdf'));
        $fileValidator = new FileUploadValidator(
            new ImageUploadValidatorTranslatorStub(),
            $detector,
            MimeTypeCatalog::default(),
        );
        $processor = $this->processor();
        $service = new UploadService(
            $fileValidator,
            new ImageUploadValidator(
                $fileValidator,
                new ImageUploadValidatorTranslatorStub(),
                $processor,
            ),
            $this->storage(),
            $this->createMock(ImageEncoderInterface::class),
            $this->staging(),
        );
        $targetDirectory = $this->basePath . '/public/uploads/files';
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
        $processor = $this->processor();
        $service = new UploadService(
            $fileValidator,
            new ImageUploadValidator(
                $fileValidator,
                new ImageUploadValidatorTranslatorStub(),
                $processor,
            ),
            $this->storage(),
            $this->createMock(ImageEncoderInterface::class),
            $this->staging(),
        );
        $upload = $this->upload('image.jpg', $this->pngBytes());

        try {
            $service->uploadImage($upload, $this->options($reencode));
            self::fail('The MIME mismatch must be rejected before storage.');
        } catch (UploadValidationException) {
            self::assertSame(0, $upload->moveCalls);
            self::assertSame(1, $detector->calls);
            self::assertDirectoryDoesNotExist($this->basePath . '/public/uploads/images');
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

        return new ImageUploadValidator($fileValidator, $translator, $this->processor());
    }

    private function options(bool $reencode = true): ImageUploadOptions
    {
        return new ImageUploadOptions(
            targetDirectory: $this->basePath . '/public/uploads/images',
            targetRelativeDirectory: 'uploads/images',
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

    private function snapshot(string $filename, string $contents): StagedUpload
    {
        $path = tempnam(sys_get_temp_dir(), 'lemonade-image-snapshot-');

        if ($path === false) {
            self::fail('Temporary image snapshot cannot be created.');
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

    private function pngBytes(): string
    {
        $image = imagecreatetruecolor(1, 1);

        if ($image === false || !ob_start()) {
            throw new \RuntimeException('PNG fixture cannot be created.');
        }

        imagepng($image);
        $contents = ob_get_clean();
        unset($image);

        if (!is_string($contents)) {
            throw new \RuntimeException('PNG fixture cannot be encoded.');
        }

        return $contents;
    }

    private function truncatedPngBytes(): string
    {
        $contents = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL0jQAAAABJRU5ErkJggg==',
            true,
        );

        if ($contents === false) {
            throw new \RuntimeException('Truncated PNG fixture cannot be decoded.');
        }

        return $contents;
    }

    private function service(): UploadService
    {
        $translator = new ImageUploadValidatorTranslatorStub();
        $fileValidator = new FileUploadValidator(
            $translator,
            new MimeTypeDetector(),
            MimeTypeCatalog::default(),
        );
        $processor = $this->processor();

        return new UploadService(
            $fileValidator,
            new ImageUploadValidator($fileValidator, $translator, $processor),
            $this->storage(),
            new GdImageEncoder(new GdCapabilities()),
            $this->staging(),
        );
    }

    /**
     * @return list<string>
     */
    private function stagedFiles(): array
    {
        $files = glob($this->basePath . '/storage/writable/uploads/staging/*.stage');

        return $files === false ? [] : $files;
    }

    private function storage(): UploadStorage
    {
        $filesystem = $this->filesystem();
        $context = $this->context();

        return new UploadStorage(
            new ImageUploadValidatorTranslatorStub(),
            $filesystem,
            new UploadPathPolicy($context),
        );
    }

    private function staging(): UploadStaging
    {
        return new UploadStaging(
            new ImageUploadValidatorTranslatorStub(),
            $this->filesystem(),
            new UploadPathPolicy($this->context()),
        );
    }

    private function processor(): GdImageProcessor
    {
        return new GdImageProcessor(new GdCapabilities());
    }

    private function context(): ApplicationContext
    {
        return new ApplicationContext(
            Environment::Testing,
            new Path($this->basePath, $this->basePath . '/public'),
            DebugMode::disabled(),
        );
    }

    private function filesystem(): Filesystem
    {
        $directoryManager = new DirectoryManager();

        return new Filesystem(
            $directoryManager,
            new FileManager(),
            new LockManager($directoryManager),
        );
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
