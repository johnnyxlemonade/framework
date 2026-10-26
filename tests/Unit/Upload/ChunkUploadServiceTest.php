<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Upload;

use Lemonade\Framework\Core\Context\ApplicationContextFactory;
use Lemonade\Framework\Filesystem\Filesystem;
use Lemonade\Framework\Filesystem\Manager\DirectoryManager;
use Lemonade\Framework\Filesystem\Manager\FileManager;
use Lemonade\Framework\Filesystem\Manager\LockManager;
use Lemonade\Framework\Image\Gd\GdCapabilities;
use Lemonade\Framework\Image\Gd\GdImageEncoder;
use Lemonade\Framework\Image\Gd\GdImageProcessor;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Mime\MimeTypeCatalog;
use Lemonade\Framework\Mime\MimeTypeDetector;
use Lemonade\Framework\Upload\Chunk\ChunkUploadConfig;
use Lemonade\Framework\Upload\Chunk\ChunkUploadSession;
use Lemonade\Framework\Upload\Chunk\ChunkUploadService;
use Lemonade\Framework\Upload\Chunk\FilesystemChunkUploadSessionStore;
use Lemonade\Framework\Upload\Config\UploadConfigDefinition;
use Lemonade\Framework\Upload\Config\UploadConfigResolver;
use Lemonade\Framework\Upload\Exception\UploadStorageException;
use Lemonade\Framework\Upload\Exception\UploadValidationException;
use Lemonade\Framework\Upload\FileUploadValidator;
use Lemonade\Framework\Upload\ImageUploadValidator;
use Lemonade\Framework\Upload\Staging\UploadStaging;
use Lemonade\Framework\Upload\Storage\UploadStorage;
use Lemonade\Framework\Upload\UploadFactory;
use Lemonade\Framework\Upload\UploadPathPolicy;
use Lemonade\Framework\Upload\UploadService;
use Lemonade\Framework\Upload\UploadVisibility;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\TestCase;

final class ChunkUploadServiceTest extends TestCase
{
    private string $basePath = '';
    /** @var FilesystemChunkUploadSessionStore */
    private $store;
    /** @var ChunkUploadService */
    private $service;

    protected function setUp(): void
    {
        $this->basePath = sys_get_temp_dir() . '/lemonade-chunk-upload-' . bin2hex(random_bytes(8));
        mkdir($this->basePath . '/public', 0700, true);

        $context = (new ApplicationContextFactory())->create($this->basePath);
        $directoryManager = new DirectoryManager();
        $filesystem = new Filesystem($directoryManager, new FileManager(), new LockManager($directoryManager));
        $translator = new ChunkUploadTranslatorStub();
        $validator = new FileUploadValidator($translator, new MimeTypeDetector(), MimeTypeCatalog::default());
        $pathPolicy = new UploadPathPolicy($context);
        $imageProcessor = new GdImageProcessor(new GdCapabilities());
        $storage = new UploadStorage($translator, $filesystem, $pathPolicy);
        $uploadService = new UploadService(
            $validator,
            new ImageUploadValidator($validator, $translator, $imageProcessor),
            $storage,
            new GdImageEncoder(new GdCapabilities()),
            new UploadStaging($translator, $filesystem, $pathPolicy),
        );
        $factory = new UploadFactory(
            (new UploadConfigResolver())->resolve(
                UploadConfigDefinition::create()
                    ->fileProfile('shared', 'files', 8, ['txt'])
                    ->fileProfile(
                        profile: 'private',
                        targetDirectory: 'documents',
                        maxBytes: 8,
                        allowedExtensions: ['txt'],
                        visibility: UploadVisibility::Private,
                    )
                    ->imageProfile('shared', 'images', 4096, ['png'], reencode: false),
            ),
            $uploadService,
            new ServerRequest('POST', '/uploads'),
            $translator,
            $context,
        );

        $config = new ChunkUploadConfig(chunkBytes: 4, ttlSeconds: 3600);
        $this->store = new FilesystemChunkUploadSessionStore(
            $filesystem,
            $pathPolicy,
            $config,
        );
        $this->service = new ChunkUploadService(
            $config,
            $this->store,
            $factory,
            $storage,
            $uploadService,
        );
    }

    protected function tearDown(): void
    {
        if (is_dir($this->basePath)) {
            (new DirectoryManager())->delete($this->basePath);
        }
    }

    public function testStartUsesKindSpecificProfileAndExposesConfiguredChunkSize(): void
    {
        $file = $this->service->start('file', 'shared', 'note.txt', 8);
        $image = $this->service->start('image', 'shared', 'image.png', 8);

        self::assertSame(4, $this->service->chunkBytes());
        self::assertSame('file', $file->kind());
        self::assertSame('image', $image->kind());
        self::assertSame(8, $file->maxBytes());
        self::assertSame(4096, $image->maxBytes());
    }

    public function testDefaultChunkSizeIsTwoMebibytes(): void
    {
        $config = new ChunkUploadConfig();

        self::assertSame(2 * 1024 * 1024, $config->chunkBytes());
        self::assertSame(3600, $config->ttlSeconds());
        self::assertSame(128, $config->maxActiveSessions());
        self::assertSame(512 * 1024 * 1024, $config->maxReservedBytes());
        self::assertSame(1024, $config->maxChunksPerSession());
        self::assertSame(16 * 1024, $config->maxContextBytes());
        self::assertSame(24 * 3600, $config->stagingOrphanTtlSeconds());
        self::assertSame(128, $config->cleanupBatchSize());
    }

    public function testOversizedStartDoesNotCreateTemporaryData(): void
    {
        $this->expectException(UploadValidationException::class);

        try {
            $this->service->start('file', 'shared', 'note.txt', 9);
        } finally {
            self::assertFalse(is_dir($this->store->root()));
        }
    }

    public function testAppendIsSequentialAndRejectsOversizedOrEmptyPayloads(): void
    {
        $session = $this->service->start('file', 'shared', 'note.txt', 6);
        $session = $this->service->append($session->uploadId(), 0, Stream::create('abcd'));

        self::assertSame(4, $session->currentOffset());

        $this->expectException(UploadValidationException::class);
        $this->service->append($session->uploadId(), 4, Stream::create('abcde'));
    }

    public function testFailedAppendLeavesExistingPayloadAndOffsetUntouched(): void
    {
        $session = $this->service->start('file', 'shared', 'note.txt', 6);
        $session = $this->service->append($session->uploadId(), 0, Stream::create('abcd'));

        try {
            $this->service->append($session->uploadId(), 3, Stream::create('ef'));
            self::fail('An incorrect offset must be rejected.');
        } catch (UploadValidationException) {
            self::assertSame(4, $this->store->payloadSize($session->uploadId()));
            self::assertSame(4, $this->store->load($session->uploadId())?->currentOffset());
        }

        $retried = $this->service->append($session->uploadId(), 4, Stream::create('ef'));

        self::assertSame(6, $retried->currentOffset());
    }

    public function testCompleteUsesTheExistingFilePipelineAndRemovesSession(): void
    {
        $session = $this->service->start('file', 'shared', 'note.txt', 5);
        $session = $this->service->append($session->uploadId(), 0, Stream::create('hell'));
        $session = $this->service->append($session->uploadId(), 4, Stream::create('o'));

        $uploaded = $this->service->complete($session->uploadId());

        self::assertSame('text/plain', $uploaded->mimeType());
        self::assertSame(5, $uploaded->sizeBytes());
        self::assertFileExists($uploaded->storedPath());
        self::assertNull($this->store->load($session->uploadId()));
        self::assertSame([], $this->stagedFiles());
    }

    public function testCompleteStoresPrivateFileOutsidePublicRootWithoutPublicPath(): void
    {
        $session = $this->service->start('file', 'private', 'note.txt', 5);
        $session = $this->service->append($session->uploadId(), 0, Stream::create('hell'));
        $this->service->append($session->uploadId(), 4, Stream::create('o'));

        $uploaded = $this->service->complete($session->uploadId());

        self::assertSame(UploadVisibility::Private, $uploaded->visibility());
        self::assertNull($uploaded->publicRelativePath());
        self::assertStringStartsWith(
            $this->basePath . '/storage/writable/uploads/files/documents/',
            $uploaded->storedPath(),
        );
        self::assertStringStartsNotWith($this->basePath . '/public/', $uploaded->storedPath());

        if (DIRECTORY_SEPARATOR !== '\\') {
            self::assertSame(0600, fileperms($uploaded->storedPath()) & 0777);
            self::assertSame(0700, fileperms(dirname($uploaded->storedPath())) & 0777);
        }
    }

    public function testCompleteValidationFailureRemovesSession(): void
    {
        $session = $this->service->start('file', 'shared', 'wrong.pdf', 5);
        $this->service->append($session->uploadId(), 0, Stream::create('hell'));
        $this->service->append($session->uploadId(), 4, Stream::create('o'));

        $this->expectException(UploadValidationException::class);

        try {
            $this->service->complete($session->uploadId());
        } finally {
            self::assertNull($this->store->load($session->uploadId()));
        }
    }

    public function testCompleteUsesTheExistingImageValidator(): void
    {
        $image = imagecreatetruecolor(1, 1);
        self::assertNotFalse($image);
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        unset($image);

        if (!is_string($bytes)) {
            self::fail('PNG fixture cannot be generated.');
        }

        $session = $this->service->start('image', 'shared', 'image.png', strlen($bytes));
        $session = $this->appendContent($session->uploadId(), $bytes);

        $uploaded = $this->service->complete($session->uploadId());

        self::assertInstanceOf(\Lemonade\Framework\Upload\ValueObject\UploadedImage::class, $uploaded);
        self::assertSame(1, $uploaded->width());
        self::assertSame(1, $uploaded->height());
        self::assertNull($this->store->load($session->uploadId()));
        self::assertSame([], $this->stagedFiles());
    }

    public function testPayloadMismatchIsRejectedWithoutUsingTheFinalPipeline(): void
    {
        $session = $this->service->start('file', 'shared', 'note.txt', 4);
        $this->service->append($session->uploadId(), 0, Stream::create('abcd'));
        file_put_contents($this->store->payloadPath($session->uploadId()), 'x', FILE_APPEND);

        $this->expectException(UploadValidationException::class);
        $this->service->complete($session->uploadId());
    }

    public function testCleanupRemovesExpiredAndOldCorruptedSessionsOnly(): void
    {
        $expired = new ChunkUploadSession(
            uploadId: str_repeat('a', 64),
            kind: 'file',
            profile: 'shared',
            originalFilename: 'old.txt',
            declaredSize: 1,
            maxBytes: 8,
            currentOffset: 0,
            chunkCount: 0,
            maxChunks: 1024,
            createdAt: 1,
            expiresAt: 2,
        );
        $active = $this->service->start('file', 'shared', 'active.txt', 1);
        $this->store->create($expired);
        @unlink(dirname($this->store->payloadPath($expired->uploadId())) . '/session.lock');

        $result = $this->service->cleanupExpired();

        self::assertSame(1, $result['removed_sessions']);
        self::assertNull($this->store->load($expired->uploadId()));
        self::assertNotNull($this->store->load($active->uploadId()));
    }

    private function appendContent(string $uploadId, string $contents): \Lemonade\Framework\Upload\Chunk\ChunkUploadSession
    {
        $offset = 0;
        $session = null;

        while ($offset < strlen($contents)) {
            $part = substr($contents, $offset, 4);
            $session = $this->service->append($uploadId, $offset, Stream::create($part));
            $offset += strlen($part);
        }

        if (!$session instanceof ChunkUploadSession) {
            self::fail('Chunk upload session was not appended.');
        }

        return $session;
    }

    /**
     * @return list<string>
     */
    private function stagedFiles(): array
    {
        $files = glob($this->basePath . '/storage/writable/uploads/staging/*.stage');

        return $files === false ? [] : $files;
    }
}

final class ChunkUploadTranslatorStub implements TranslatorInterface
{
    public function setLocale(?string $locale): self { return $this; }
    public function locale(): ?string { return null; }
    public function get(string $key, array $replacements = [], ?string $locale = null): string { return $key; }
    public function group(string $group, ?string $locale = null): array { return [$group => $group]; }
    public function all(?string $locale = null): array { return ['messages' => ['ok' => 'ok']]; }
}
