<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Upload;

use Closure;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Filesystem\Filesystem;
use Lemonade\Framework\Filesystem\Manager\DirectoryManager;
use Lemonade\Framework\Filesystem\Manager\FileManager;
use Lemonade\Framework\Filesystem\Manager\LockManager;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Upload\Exception\UploadValidationException;
use Lemonade\Framework\Upload\Chunk\ChunkUploadConfig;
use Lemonade\Framework\Upload\Staging\UploadStaging;
use Lemonade\Framework\Upload\UploadPathPolicy;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;

final class UploadStagingTest extends TestCase
{
    private string $basePath = '';
    private UploadStaging $staging; // @phpstan-ignore property.uninitialized (initialized by PHPUnit setUp)
    private UploadPathPolicy $pathPolicy; // @phpstan-ignore property.uninitialized (initialized by PHPUnit setUp)

    protected function setUp(): void
    {
        $this->basePath = sys_get_temp_dir() . '/lemonade-staging-' . bin2hex(random_bytes(8));
        mkdir($this->basePath . '/public', 0700, true);

        $context = new ApplicationContext(
            Environment::Testing,
            new Path($this->basePath, $this->basePath . '/public'),
            DebugMode::disabled(),
        );
        $directoryManager = new DirectoryManager();
        $filesystem = new Filesystem(
            $directoryManager,
            new FileManager(),
            new LockManager($directoryManager),
        );
        $this->pathPolicy = new UploadPathPolicy($context);
        $this->staging = new UploadStaging(
            new UploadStagingTranslatorStub(),
            $filesystem,
            $this->pathPolicy,
        );
    }

    protected function tearDown(): void
    {
        if (is_dir($this->basePath)) {
            (new DirectoryManager())->delete($this->basePath);
        }
    }

    #[DataProvider('declaredSizeProvider')]
    public function testUsesStreamedSizeInsteadOfClientDeclaration(?int $declaredSize): void
    {
        $upload = new StagingUploadedFile(
            Stream::create('hello'),
            'note.txt',
            $declaredSize,
        );

        $snapshot = $this->staging->stage($upload, 5);

        self::assertSame(5, $snapshot->sizeBytes());
        self::assertSame('hello', file_get_contents($snapshot->path()));
        $stagingRoot = $this->pathPolicy->stagingRoot();

        if ($stagingRoot === '') {
            self::fail('Staging root cannot be empty.');
        }

        self::assertStringStartsWith($stagingRoot, $snapshot->path());
        self::assertStringStartsNotWith($this->basePath . '/public/', $snapshot->path());

        $this->staging->discard($snapshot);

        self::assertFileDoesNotExist($snapshot->path());
    }

    /**
     * @return iterable<string, array{int|null}>
     */
    public static function declaredSizeProvider(): iterable
    {
        yield 'unknown' => [null];
        yield 'false empty value' => [0];
        yield 'false small value' => [1];
        yield 'false oversized value' => [999];
    }

    public function testStopsAtTheLimitAndRemovesThePartialSnapshot(): void
    {
        $upload = new StagingUploadedFile(
            Stream::create(str_repeat('x', 9)),
            'oversized.txt',
            null,
        );

        $this->expectException(UploadValidationException::class);

        try {
            $this->staging->stage($upload, 8);
        } finally {
            self::assertSame([], $this->stagedFiles());
        }
    }

    public function testDoesNotConsultAThrowingClientSizeAccessor(): void
    {
        $snapshot = $this->staging->stage(
            new StagingUploadedFile(
                Stream::create('actual'),
                'note.txt',
                null,
                sizeThrows: true,
            ),
            6,
        );

        self::assertSame(6, $snapshot->sizeBytes());
        $this->staging->discard($snapshot);
    }

    public function testRejectsEmptyAndTransportFailedInputsWithoutLeavingSnapshots(): void
    {
        try {
            $this->staging->stage(
                new StagingUploadedFile(Stream::create(''), 'empty.txt', 99),
                8,
            );
            self::fail('An empty stream must be rejected.');
        } catch (UploadValidationException) {
            self::assertSame([], $this->stagedFiles());
        }

        $this->expectException(UploadValidationException::class);

        try {
            $this->staging->stage(
                new StagingUploadedFile(
                    Stream::create('partial'),
                    'partial.txt',
                    7,
                    UPLOAD_ERR_PARTIAL,
                ),
                8,
            );
        } finally {
            self::assertSame([], $this->stagedFiles());
        }
    }

    public function testReadFailureRemovesThePartialSnapshot(): void
    {
        $upload = new StagingUploadedFile(
            new BrokenReadStream(Stream::create('abcdefgh')),
            'broken.txt',
            null,
        );

        $this->expectException(UploadValidationException::class);

        try {
            $this->staging->stage($upload, 16);
        } finally {
            self::assertSame([], $this->stagedFiles());
        }
    }

    public function testIncompleteStreamRemovesThePartialSnapshot(): void
    {
        $upload = new StagingUploadedFile(
            new StallingReadStream(Stream::create('abcdefgh')),
            'incomplete.txt',
            null,
        );

        $this->expectException(UploadValidationException::class);

        try {
            $this->staging->stage($upload, 16);
        } finally {
            self::assertSame([], $this->stagedFiles());
        }
    }

    public function testCleanupCannotRemoveSnapshotWhileItsStreamIsStillBeingCopied(): void
    {
        $cleanupResult = null;
        $activePath = null;
        $now = time();
        $stream = new CleanupDuringReadStream(
            Stream::create('slow bytes'),
            function () use (&$cleanupResult, &$activePath, $now): void {
                $files = $this->stagedFiles();
                self::assertCount(1, $files);
                $activePath = $files[0];
                touch(
                    $activePath,
                    $now - ChunkUploadConfig::DEFAULT_STAGING_ORPHAN_TTL_SECONDS - 1,
                );
                $cleanupResult = $this->staging->cleanupOrphans($now);
            },
        );

        $snapshot = $this->staging->stage(
            new StagingUploadedFile($stream, 'slow.txt', null),
            1024,
        );

        self::assertSame(0, $cleanupResult);
        self::assertSame($activePath, $snapshot->path());
        self::assertFileExists($snapshot->path());
        $this->staging->discard($snapshot);
    }

    public function testSourceMutationCannotChangeTheCreatedSnapshot(): void
    {
        $source = $this->basePath . '/source.txt';
        file_put_contents($source, 'validated bytes');
        $resource = fopen($source, 'rb');

        if ($resource === false) {
            self::fail('Source fixture cannot be opened.');
        }

        $snapshot = $this->staging->stage(
            new StagingUploadedFile(Stream::create($resource), 'note.txt', null),
            1024,
        );

        file_put_contents($source, 'attacker replacement');

        self::assertSame('validated bytes', file_get_contents($snapshot->path()));
        $this->staging->assertIntact($snapshot);
        $this->staging->discard($snapshot);
    }

    public function testCleanupSkipsActiveSnapshotAndReclaimsItAfterLeaseRelease(): void
    {
        $snapshot = $this->staging->stage(
            new StagingUploadedFile(Stream::create('orphan'), 'orphan.txt', null),
            1024,
        );
        $now = time();
        touch(
            $snapshot->path(),
            $now - ChunkUploadConfig::DEFAULT_STAGING_ORPHAN_TTL_SECONDS - 1,
        );

        self::assertSame(0, $this->staging->cleanupOrphans($now));
        self::assertFileExists($snapshot->path());

        $snapshot->releaseLease();

        self::assertSame(1, $this->staging->cleanupOrphans($now));
        self::assertFileDoesNotExist($snapshot->path());
    }

    public function testOrdinaryUploadReclaimsOrphanLeftByTerminatedProcess(): void
    {
        if (!function_exists('pcntl_fork')) {
            self::markTestSkipped('The pcntl extension is required to simulate process termination.');
        }

        $pathFile = $this->basePath . '/orphan-path';
        $processId = pcntl_fork();

        if ($processId === -1) {
            self::fail('Orphan simulation process cannot be created.');
        }

        if ($processId === 0) {
            $snapshot = $this->staging->stage(
                new StagingUploadedFile(Stream::create('crashed'), 'crashed.txt', null),
                1024,
            );
            file_put_contents($pathFile, $snapshot->path());
            exit(0);
        }

        $status = 0;
        pcntl_waitpid($processId, $status);
        self::assertSame(0, pcntl_wexitstatus($status));
        $orphan = file_get_contents($pathFile);

        if (!is_string($orphan) || $orphan === '') {
            self::fail('Terminated process did not expose its orphan fixture path.');
        }

        touch(
            $orphan,
            time() - ChunkUploadConfig::DEFAULT_STAGING_ORPHAN_TTL_SECONDS - 1,
        );
        $replacement = $this->staging->stage(
            new StagingUploadedFile(Stream::create('replacement'), 'replacement.txt', null),
            1024,
        );

        self::assertFileDoesNotExist($orphan);
        $this->staging->discard($replacement);
    }

    /**
     * @return list<string>
     */
    private function stagedFiles(): array
    {
        $files = glob($this->pathPolicy->stagingRoot() . '/*.stage');

        return $files === false ? [] : $files;
    }
}

final readonly class StagingUploadedFile implements UploadedFileInterface
{
    public function __construct(
        private StreamInterface $stream,
        private string $filename,
        private ?int $declaredSize,
        private int $error = UPLOAD_ERR_OK,
        private bool $sizeThrows = false,
    ) {
    }

    public function getStream(): StreamInterface
    {
        return $this->stream;
    }

    public function moveTo(string $targetPath): void
    {
        throw new \LogicException('Staging must never call moveTo().');
    }

    public function getSize(): ?int
    {
        if ($this->sizeThrows) {
            throw new \RuntimeException('Client size must not be consulted.');
        }

        return $this->declaredSize;
    }

    public function getError(): int
    {
        return $this->error;
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

abstract class DelegatingTestStream implements StreamInterface
{
    public function __construct(protected readonly StreamInterface $inner)
    {
    }

    public function __toString(): string
    {
        return (string) $this->inner;
    }

    public function close(): void
    {
        $this->inner->close();
    }

    public function detach()
    {
        return $this->inner->detach();
    }

    public function getSize(): ?int
    {
        return $this->inner->getSize();
    }

    public function tell(): int
    {
        return $this->inner->tell();
    }

    public function eof(): bool
    {
        return $this->inner->eof();
    }

    public function isSeekable(): bool
    {
        return $this->inner->isSeekable();
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        $this->inner->seek($offset, $whence);
    }

    public function rewind(): void
    {
        $this->inner->rewind();
    }

    public function isWritable(): bool
    {
        return $this->inner->isWritable();
    }

    public function write(string $string): int
    {
        return $this->inner->write($string);
    }

    public function isReadable(): bool
    {
        return $this->inner->isReadable();
    }

    public function getContents(): string
    {
        return $this->inner->getContents();
    }

    public function getMetadata(?string $key = null)
    {
        return $this->inner->getMetadata($key);
    }
}

final class BrokenReadStream extends DelegatingTestStream
{
    private int $reads = 0;

    public function read(int $length): string
    {
        ++$this->reads;

        if ($this->reads > 1) {
            throw new \RuntimeException('Simulated stream read failure.');
        }

        return $this->inner->read(min(4, $length));
    }
}

final class StallingReadStream extends DelegatingTestStream
{
    private int $reads = 0;

    public function read(int $length): string
    {
        ++$this->reads;

        if ($this->reads > 1) {
            return '';
        }

        return $this->inner->read(min(4, $length));
    }
}

final class CleanupDuringReadStream extends DelegatingTestStream
{
    private bool $invoked = false;

    public function __construct(
        StreamInterface $inner,
        private readonly Closure $duringRead,
    ) {
        parent::__construct($inner);
    }

    public function read(int $length): string
    {
        if (!$this->invoked) {
            $this->invoked = true;
            ($this->duringRead)();
        }

        return $this->inner->read($length);
    }
}

final class UploadStagingTranslatorStub implements TranslatorInterface
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
