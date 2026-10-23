<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Upload;

use Closure;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\ApplicationContextFactory;
use Lemonade\Framework\Filesystem\Filesystem;
use Lemonade\Framework\Filesystem\Manager\DirectoryManager;
use Lemonade\Framework\Filesystem\Manager\FileManager;
use Lemonade\Framework\Filesystem\Manager\LockManager;
use Lemonade\Framework\Image\Contract\ImageEncoderInterface;
use Lemonade\Framework\Image\Exception\ImageEncodeException;
use Lemonade\Framework\Image\Gd\GdCapabilities;
use Lemonade\Framework\Image\Gd\GdImageEncoder;
use Lemonade\Framework\Image\Gd\GdImageProcessor;
use Lemonade\Framework\Image\Value\DecodedImage;
use Lemonade\Framework\Image\Value\EncodedImage;
use Lemonade\Framework\Image\Value\ImageFormat;
use Lemonade\Framework\Image\Value\ImageQuality;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Mime\MimeTypeCatalog;
use Lemonade\Framework\Mime\MimeTypeDetector;
use Lemonade\Framework\Upload\Chunk\ChunkUploadConfig;
use Lemonade\Framework\Upload\Chunk\ChunkUploadService;
use Lemonade\Framework\Upload\Chunk\ChunkUploadSession;
use Lemonade\Framework\Upload\Chunk\FilesystemChunkUploadSessionStore;
use Lemonade\Framework\Upload\Config\UploadConfigDefinition;
use Lemonade\Framework\Upload\Config\UploadConfigResolver;
use Lemonade\Framework\Upload\Exception\ChunkUploadQuotaExceededException;
use Lemonade\Framework\Upload\Exception\UploadImageProcessingException;
use Lemonade\Framework\Upload\Exception\UploadStorageException;
use Lemonade\Framework\Upload\Exception\UploadValidationException;
use Lemonade\Framework\Upload\FileUploadValidator;
use Lemonade\Framework\Upload\ImageUploadValidator;
use Lemonade\Framework\Upload\Staging\UploadStaging;
use Lemonade\Framework\Upload\Storage\UploadStorage;
use Lemonade\Framework\Upload\UploadFactory;
use Lemonade\Framework\Upload\UploadPathPolicy;
use Lemonade\Framework\Upload\UploadService;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\TestCase;

final class ChunkUploadSecurityTest extends TestCase
{
    private string $basePath = '';
    private ApplicationContext $context; // @phpstan-ignore property.uninitialized (initialized by PHPUnit setUp)
    private Filesystem $filesystem; // @phpstan-ignore property.uninitialized (initialized by PHPUnit setUp)

    protected function setUp(): void
    {
        $this->basePath = sys_get_temp_dir() . '/lemonade-chunk-security-' . bin2hex(random_bytes(8));
        mkdir($this->basePath . '/public', 0700, true);
        $this->context = (new ApplicationContextFactory())->create($this->basePath);
        $directoryManager = new DirectoryManager();
        $this->filesystem = new Filesystem(
            $directoryManager,
            new FileManager(),
            new LockManager($directoryManager),
        );
    }

    protected function tearDown(): void
    {
        if (is_dir($this->basePath)) {
            (new DirectoryManager())->delete($this->basePath);
        }
    }

    public function testEnforcesActiveSessionAndReservedByteQuotas(): void
    {
        $activeLimit = $this->system(new ChunkUploadConfig(
            chunkBytes: 4,
            maxActiveSessions: 2,
            maxReservedBytes: 64,
        ));

        $first = $activeLimit->service->start('file', 'shared', 'first.txt', 4);
        $second = $activeLimit->service->start('file', 'shared', 'second.txt', 4);

        try {
            $activeLimit->service->start('file', 'shared', 'third.txt', 1);
            self::fail('The active-session quota must reject another reservation.');
        } catch (ChunkUploadQuotaExceededException $exception) {
            self::assertStringContainsString('active-session quota', $exception->getMessage());
        }

        $activeLimit->service->abort($first->uploadId());
        $activeLimit->service->abort($second->uploadId());

        $byteLimit = $this->system(new ChunkUploadConfig(
            chunkBytes: 4,
            maxActiveSessions: 10,
            maxReservedBytes: 6,
        ));
        $byteLimit->service->start('file', 'shared', 'first.txt', 4);

        $this->expectException(ChunkUploadQuotaExceededException::class);
        $this->expectExceptionMessage('reserved-byte quota');
        $byteLimit->service->start('file', 'shared', 'second.txt', 3);
    }

    public function testLimitsContextBytesAndChunkCount(): void
    {
        $system = $this->system(new ChunkUploadConfig(
            chunkBytes: 4,
            maxChunksPerSession: 2,
            maxContextBytes: 16,
        ));

        try {
            $system->service->start(
                'file',
                'shared',
                'context.txt',
                1,
                ['value' => str_repeat('x', 32)],
            );
            self::fail('Oversized context must be rejected before a reservation is created.');
        } catch (UploadValidationException) {
            self::assertSame([], iterator_to_array($system->store->uploadIds(), false));
        }

        $session = $system->service->start('file', 'shared', 'chunks.txt', 3);
        $session = $system->service->append($session->uploadId(), 0, Stream::create('a'));
        $session = $system->service->append($session->uploadId(), 1, Stream::create('b'));

        self::assertSame(2, $session->chunkCount());

        try {
            $system->service->append($session->uploadId(), 2, Stream::create('c'));
            self::fail('The chunk-count limit must reject another append.');
        } catch (ChunkUploadQuotaExceededException) {
            self::assertSame(2, $system->store->payloadSize($session->uploadId()));
            self::assertSame(2, $system->store->load($session->uploadId())?->chunkCount());
        }
    }

    public function testConcurrentReservationsAreAtomic(): void
    {
        $this->requireProcessControl();
        $config = new ChunkUploadConfig(
            chunkBytes: 4,
            maxActiveSessions: 1,
            maxReservedBytes: 64,
        );
        $system = $this->system($config);
        $first = $this->session(str_repeat('a', 64), $config);
        $second = $this->session(str_repeat('b', 64), $config);

        $statuses = $this->runConcurrently(
            static function () use ($system, $first): int {
                try {
                    $system->store->create($first);

                    return 0;
                } catch (ChunkUploadQuotaExceededException) {
                    return 2;
                }
            },
            static function () use ($system, $second): int {
                try {
                    $system->store->create($second);

                    return 0;
                } catch (ChunkUploadQuotaExceededException) {
                    return 2;
                }
            },
        );

        sort($statuses);
        self::assertSame([0, 2], $statuses);
        self::assertCount(1, iterator_to_array($system->store->uploadIds(), false));
    }

    public function testCreateRemainsLockedAndAutomaticCleanupSkipsItWithoutBlocking(): void
    {
        $this->requireProcessControl();
        $publishedMarker = $this->basePath . '/initialization-published';
        $releaseMarker = $this->basePath . '/initialization-release';
        $uploadId = str_repeat('d', 64);
        $config = new ChunkUploadConfig(
            chunkBytes: 4,
            ttlSeconds: 3600,
            maxActiveSessions: 4,
            cleanupBatchSize: 4,
        );
        $system = $this->system(
            $config,
            afterSessionPublished: static function (ChunkUploadSession $session) use (
                $uploadId,
                $publishedMarker,
                $releaseMarker,
            ): void {
                if ($session->uploadId() !== $uploadId) {
                    return;
                }

                file_put_contents($publishedMarker, 'ready');

                while (!is_file($releaseMarker)) {
                    usleep(1000);
                }
            },
        );
        $expired = new ChunkUploadSession(
            uploadId: $uploadId,
            kind: 'file',
            profile: 'shared',
            originalFilename: 'initializing.txt',
            declaredSize: 1,
            maxBytes: 64,
            currentOffset: 0,
            chunkCount: 0,
            maxChunks: $config->maxChunksPerSession(),
            createdAt: 1,
            expiresAt: 2,
        );
        $processId = pcntl_fork();

        if ($processId === -1) {
            self::fail('Initialization process cannot be created.');
        }

        if ($processId === 0) {
            $system->store->create($expired);
            exit(0);
        }

        try {
            $this->waitForFile($publishedMarker);

            self::assertSame(
                ['removed_sessions' => 0, 'released_bytes' => 0],
                $system->service->cleanupExpired(),
            );
            self::assertNotNull($system->store->load($uploadId));

            $created = $system->service->start('file', 'shared', 'parallel.txt', 1);
            self::assertNotNull($system->store->load($created->uploadId()));
        } finally {
            file_put_contents($releaseMarker, 'release');
            $status = 0;
            pcntl_waitpid($processId, $status);
        }

        self::assertSame(0, pcntl_wexitstatus($status));
        self::assertSame(1, $system->service->cleanupExpired()['removed_sessions']);
        self::assertNull($system->store->load($uploadId));
    }

    public function testAutomaticCleanupExaminesOnlyConfiguredSessionBatch(): void
    {
        $config = new ChunkUploadConfig(
            chunkBytes: 4,
            maxActiveSessions: 4,
            cleanupBatchSize: 1,
        );
        $system = $this->system($config);

        foreach (['e', 'f'] as $character) {
            $system->store->create(new ChunkUploadSession(
                uploadId: str_repeat($character, 64),
                kind: 'file',
                profile: 'shared',
                originalFilename: 'expired.txt',
                declaredSize: 1,
                maxBytes: 64,
                currentOffset: 0,
                chunkCount: 0,
                maxChunks: $config->maxChunksPerSession(),
                createdAt: 1,
                expiresAt: 2,
            ));
        }

        self::assertSame(1, $system->service->cleanupExpired()['removed_sessions']);
        self::assertCount(1, iterator_to_array($system->store->uploadIds(), false));
    }

    public function testRepeatedAndConcurrentCompletionPublishOnlyOnce(): void
    {
        $this->requireProcessControl();
        $system = $this->system(new ChunkUploadConfig(chunkBytes: 4));
        $session = $system->service->start('file', 'shared', 'once.txt', 5);
        $this->appendContent($system->service, $session->uploadId(), 'hello', 4);

        $statuses = $this->runConcurrently(
            static function () use ($system, $session): int {
                try {
                    $system->service->complete($session->uploadId());

                    return 0;
                } catch (UploadValidationException) {
                    return 2;
                }
            },
            static function () use ($system, $session): int {
                try {
                    $system->service->complete($session->uploadId());

                    return 0;
                } catch (UploadValidationException) {
                    return 2;
                }
            },
        );

        sort($statuses);
        self::assertSame([0, 2], $statuses);
        self::assertCount(1, $this->publishedFiles('files'));

        $this->expectException(UploadValidationException::class);
        $system->service->complete($session->uploadId());
    }

    public function testConcurrentAppendAndAbortCannotResurrectSession(): void
    {
        $this->requireProcessControl();
        $system = $this->system(new ChunkUploadConfig(chunkBytes: 4));
        $session = $system->service->start('file', 'shared', 'race.txt', 4);

        $statuses = $this->runConcurrently(
            static function () use ($system, $session): int {
                try {
                    $system->service->append($session->uploadId(), 0, Stream::create('data'));

                    return 0;
                } catch (UploadValidationException) {
                    return 2;
                }
            },
            static function () use ($system, $session): int {
                $system->service->abort($session->uploadId());

                return 0;
            },
        );

        self::assertNotContains(3, $statuses);
        self::assertNull($system->store->load($session->uploadId()));
        self::assertFileDoesNotExist($system->store->payloadPath($session->uploadId()));
    }

    public function testConcurrentCompleteAndAbortCannotPublishMoreThanOnce(): void
    {
        $this->requireProcessControl();
        $system = $this->system(new ChunkUploadConfig(chunkBytes: 4));
        $session = $system->service->start('file', 'shared', 'race.txt', 4);
        $system->service->append($session->uploadId(), 0, Stream::create('data'));

        $statuses = $this->runConcurrently(
            static function () use ($system, $session): int {
                try {
                    $system->service->complete($session->uploadId());

                    return 0;
                } catch (UploadValidationException) {
                    return 2;
                }
            },
            static function () use ($system, $session): int {
                $system->service->abort($session->uploadId());

                return 0;
            },
        );

        self::assertNotContains(3, $statuses);
        self::assertNull($system->store->load($session->uploadId()));
        self::assertLessThanOrEqual(1, count($this->publishedFiles('files')));
    }

    public function testDeterministicImageProcessingFailureTerminatesSession(): void
    {
        $system = $this->system(
            new ChunkUploadConfig(chunkBytes: 1024),
            encoder: new FailingChunkImageEncoder(),
            reencodeImage: true,
        );
        $bytes = $this->png();
        $session = $system->service->start('image', 'processed', 'image.png', strlen($bytes));
        $this->appendContent($system->service, $session->uploadId(), $bytes, 1024);

        $this->expectException(UploadImageProcessingException::class);

        try {
            $system->service->complete($session->uploadId());
        } finally {
            self::assertNull($system->store->load($session->uploadId()));
            self::assertSame([], $this->publishedFiles('images'));
        }
    }

    public function testTransientPublicationFailureLeavesSessionSafelyRetryable(): void
    {
        $config = new ChunkUploadConfig(chunkBytes: 4);
        $failing = $this->system(
            $config,
            hardLink: static fn(string $source, string $target): bool => false,
        );
        $session = $failing->service->start('file', 'shared', 'retry.txt', 5);
        $this->appendContent($failing->service, $session->uploadId(), 'hello', 4);

        try {
            $failing->service->complete($session->uploadId());
            self::fail('Unsupported hard-link publication must fail closed.');
        } catch (UploadStorageException) {
            self::assertNotNull($failing->store->load($session->uploadId()));
            self::assertSame([], $this->publishedFiles('files'));
        }

        $retry = $this->system($config);
        $uploaded = $retry->service->complete($session->uploadId());

        self::assertSame('hello', file_get_contents($uploaded->storedPath()));
        self::assertNull($retry->store->load($session->uploadId()));
    }

    public function testFinalConfirmationFailureRollsBackPublicationAndKeepsSessionRetryable(): void
    {
        $config = new ChunkUploadConfig(chunkBytes: 4);
        $failing = $this->system(
            $config,
            atomicSessionMove: static function (string $source, string $target): void {
                unset($source, $target);

                throw new UploadStorageException('Simulated atomic retirement failure.');
            },
        );
        $session = $failing->service->start('file', 'shared', 'confirm.txt', 5);
        $this->appendContent($failing->service, $session->uploadId(), 'hello', 4);

        try {
            $failing->service->complete($session->uploadId());
            self::fail('Failed session confirmation must not return a published result.');
        } catch (UploadStorageException $exception) {
            self::assertStringContainsString('rolled back', $exception->getMessage());
            self::assertNotNull($failing->store->load($session->uploadId()));
            self::assertSame([], $this->publishedFiles('files'));
        }

        $retry = $this->system($config);
        $uploaded = $retry->service->complete($session->uploadId());

        self::assertSame('hello', file_get_contents($uploaded->storedPath()));
        self::assertNull($retry->store->load($session->uploadId()));
    }

    public function testRetryAfterProcessDeathReusesIdenticalPublishedFile(): void
    {
        $this->requireProcessControl();
        $config = new ChunkUploadConfig(chunkBytes: 4);
        $crashing = $this->system(
            $config,
            atomicSessionMove: static function (string $source, string $target): void {
                unset($source, $target);
                exit(0);
            },
        );
        $session = $crashing->service->start('file', 'shared', 'crash.txt', 5);
        $this->appendContent($crashing->service, $session->uploadId(), 'hello', 4);
        $processId = pcntl_fork();

        if ($processId === -1) {
            self::fail('Completion crash process cannot be created.');
        }

        if ($processId === 0) {
            $crashing->service->complete($session->uploadId());
            exit(4);
        }

        $status = 0;
        pcntl_waitpid($processId, $status);

        self::assertSame(0, pcntl_wexitstatus($status));
        self::assertNotNull($crashing->store->load($session->uploadId()));
        self::assertCount(1, $this->publishedFiles('files'));

        $retry = $this->system($config);
        $uploaded = $retry->service->complete($session->uploadId());

        self::assertSame($session->publicationToken() . '.txt', $uploaded->storedFilename());
        self::assertSame('hello', file_get_contents($uploaded->storedPath()));
        self::assertCount(1, $this->publishedFiles('files'));
        self::assertNull($retry->store->load($session->uploadId()));
    }

    public function testStartingSessionAutomaticallyCleansExpiredButNotActiveSessions(): void
    {
        $config = new ChunkUploadConfig(chunkBytes: 4, ttlSeconds: 3600);
        $system = $this->system($config);
        $active = $system->service->start('file', 'shared', 'active.txt', 1);
        $expired = new ChunkUploadSession(
            uploadId: str_repeat('c', 64),
            kind: 'file',
            profile: 'shared',
            originalFilename: 'expired.txt',
            declaredSize: 1,
            maxBytes: 64,
            currentOffset: 0,
            chunkCount: 0,
            maxChunks: $config->maxChunksPerSession(),
            createdAt: 1,
            expiresAt: 2,
        );
        $system->store->create($expired);

        $created = $system->service->start('file', 'shared', 'new.txt', 1);

        self::assertNull($system->store->load($expired->uploadId()));
        self::assertNotNull($system->store->load($active->uploadId()));
        self::assertNotNull($system->store->load($created->uploadId()));
    }

    public function testChunkStorageUsesPrivatePermissionsAndRejectsPayloadSymlink(): void
    {
        $system = $this->system(new ChunkUploadConfig(chunkBytes: 4));
        $session = $system->service->start('file', 'shared', 'safe.txt', 4);
        $system->service->append($session->uploadId(), 0, Stream::create('data'));
        $payload = $system->store->payloadPath($session->uploadId());
        $sessionDirectory = dirname($payload);

        if (DIRECTORY_SEPARATOR !== '\\') {
            self::assertSame(0700, fileperms($system->store->root()) & 0777);
            self::assertSame(0700, fileperms(dirname($sessionDirectory)) & 0777);
            self::assertSame(0700, fileperms($sessionDirectory) & 0777);
            self::assertSame(0600, fileperms($payload) & 0777);
            self::assertSame(0600, fileperms($sessionDirectory . '/meta.json') & 0777);
            self::assertSame(0600, fileperms($sessionDirectory . '/session.lock') & 0777);
            self::assertSame(0600, fileperms($system->store->root() . '/.quota.lock') & 0777);
        }

        $system->service->abort($session->uploadId());
        $replacement = $system->service->start('file', 'shared', 'linked.txt', 4);
        $outside = $this->basePath . '/outside.txt';
        file_put_contents($outside, 'unchanged');

        if (!function_exists('symlink') || !@symlink($outside, $system->store->payloadPath($replacement->uploadId()))) {
            self::markTestSkipped('Symbolic links are unavailable.');
        }

        $this->expectException(UploadStorageException::class);

        try {
            $system->service->append($replacement->uploadId(), 0, Stream::create('evil'));
        } finally {
            self::assertSame('unchanged', file_get_contents($outside));
        }
    }

    private function system(
        ChunkUploadConfig $config,
        ?Closure $hardLink = null,
        ?ImageEncoderInterface $encoder = null,
        bool $reencodeImage = false,
        ?Closure $atomicSessionMove = null,
        ?Closure $afterSessionPublished = null,
    ): ChunkUploadTestSystem {
        $translator = new ChunkSecurityTranslatorStub();
        $pathPolicy = new UploadPathPolicy($this->context);
        $storage = new UploadStorage(
            $translator,
            $this->filesystem,
            $pathPolicy,
            $hardLink,
        );
        $validator = new FileUploadValidator(
            $translator,
            new MimeTypeDetector(),
            MimeTypeCatalog::default(),
        );
        $imageProcessor = new GdImageProcessor(new GdCapabilities());
        $uploadService = new UploadService(
            $validator,
            new ImageUploadValidator($validator, $translator, $imageProcessor),
            $storage,
            $encoder ?? new GdImageEncoder(new GdCapabilities()),
            new UploadStaging(
                $translator,
                $this->filesystem,
                $pathPolicy,
                $config,
            ),
        );
        $definition = UploadConfigDefinition::create()
            ->fileProfile('shared', 'files', 64, ['txt'])
            ->imageProfile('processed', 'images', 4096, ['png'], reencode: $reencodeImage);
        $factory = new UploadFactory(
            (new UploadConfigResolver())->resolve($definition),
            $uploadService,
            new ServerRequest('POST', '/uploads'),
            $translator,
            $this->context,
        );
        $store = new FilesystemChunkUploadSessionStore(
            $this->filesystem,
            $pathPolicy,
            $config,
            $atomicSessionMove,
            $afterSessionPublished,
        );

        return new ChunkUploadTestSystem(
            new ChunkUploadService(
                $config,
                $store,
                $factory,
                $storage,
                $uploadService,
            ),
            $store,
        );
    }

    private function session(string $uploadId, ChunkUploadConfig $config): ChunkUploadSession
    {
        return new ChunkUploadSession(
            uploadId: $uploadId,
            kind: 'file',
            profile: 'shared',
            originalFilename: 'concurrent.txt',
            declaredSize: 1,
            maxBytes: 64,
            currentOffset: 0,
            chunkCount: 0,
            maxChunks: $config->maxChunksPerSession(),
            createdAt: time(),
            expiresAt: time() + $config->ttlSeconds(),
        );
    }

    private function appendContent(
        ChunkUploadService $service,
        string $uploadId,
        string $contents,
        int $chunkBytes,
    ): void {
        $offset = 0;

        while ($offset < strlen($contents)) {
            $chunk = substr($contents, $offset, $chunkBytes);
            $service->append($uploadId, $offset, Stream::create($chunk));
            $offset += strlen($chunk);
        }
    }

    /**
     * @return list<int>
     */
    private function runConcurrently(callable $first, callable $second): array
    {
        $gate = $this->basePath . '/concurrency.gate';
        $children = [];

        foreach ([$first, $second] as $operation) {
            $processId = pcntl_fork();

            if ($processId === -1) {
                self::fail('Concurrent test process cannot be created.');
            }

            if ($processId === 0) {
                while (!is_file($gate)) {
                    usleep(1000);
                }

                try {
                    exit($operation());
                } catch (\Throwable $exception) {
                    file_put_contents(
                        $gate . '.failure.' . getmypid(),
                        $exception::class . ': ' . $exception->getMessage(),
                    );
                    exit(3);
                }
            }

            $children[] = $processId;
        }

        file_put_contents($gate, 'go');
        $statuses = [];

        foreach ($children as $processId) {
            $status = 0;
            pcntl_waitpid($processId, $status);
            $exitStatus = pcntl_wexitstatus($status);

            if ($exitStatus === false) {
                self::fail('Concurrent child exit status cannot be read.');
            }

            $statuses[] = $exitStatus;
        }

        @unlink($gate);

        $failureFiles = glob($gate . '.failure.*');

        if (is_array($failureFiles) && $failureFiles !== []) {
            $messages = array_map(
                static fn(string $path): string => (string) file_get_contents($path),
                $failureFiles,
            );

            self::fail('Concurrent child failed unexpectedly: ' . implode('; ', $messages));
        }

        return $statuses;
    }

    /**
     * @return list<string>
     */
    private function publishedFiles(string $directory): array
    {
        $files = glob($this->basePath . '/public/uploads/' . $directory . '/*');

        return $files === false ? [] : $files;
    }

    private function png(): string
    {
        $image = imagecreatetruecolor(1, 1);
        self::assertNotFalse($image);
        ob_start();
        imagepng($image);
        $contents = ob_get_clean();
        imagedestroy($image);

        if (!is_string($contents)) {
            self::fail('PNG fixture cannot be generated.');
        }

        return $contents;
    }

    private function requireProcessControl(): void
    {
        if (!function_exists('pcntl_fork')) {
            self::markTestSkipped('The pcntl extension is required for process concurrency coverage.');
        }
    }

    private function waitForFile(string $path): void
    {
        $deadline = microtime(true) + 5.0;

        while (!is_file($path)) {
            if (microtime(true) >= $deadline) {
                self::fail('Concurrent operation did not reach its synchronization point.');
            }

            usleep(1000);
        }
    }
}

final readonly class ChunkUploadTestSystem
{
    public function __construct(
        public ChunkUploadService $service,
        public FilesystemChunkUploadSessionStore $store,
    ) {
    }
}

final class FailingChunkImageEncoder implements ImageEncoderInterface
{
    public function encode(DecodedImage $image, ImageFormat $format, ImageQuality $quality): EncodedImage
    {
        unset($image, $format, $quality);

        throw new ImageEncodeException('Deterministic encoder failure.');
    }
}

final class ChunkSecurityTranslatorStub implements TranslatorInterface
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

        return [];
    }
}
