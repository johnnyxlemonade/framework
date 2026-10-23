<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload\Storage;

use Closure;
use Lemonade\Framework\Filesystem\Filesystem;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Upload\Chunk\ChunkUploadConfig;
use Lemonade\Framework\Upload\Exception\UploadStorageException;
use Lemonade\Framework\Upload\Staging\StagedUpload;
use Lemonade\Framework\Upload\UploadPathPolicy;
use Lemonade\Framework\Upload\UploadVisibility;
use Lemonade\Framework\Upload\ValueObject\UploadedFile;

/**
 * Publishes caller-asserted snapshots inside visibility-specific storage boundaries.
 *
 * This trusted-code boundary enforces paths, permissions, identity, and atomic no-clobber publication, but does not
 * perform MIME, risk, or image validation. Applications must use UploadFactory for untrusted input.
 *
 * @internal Applications should use UploadFactory so validation and storage policy cannot be separated.
 */
final readonly class UploadStorage
{
    private const int COPY_BYTES = 8192;
    private const int CREATE_ATTEMPTS = 10;
    private const string TEMPORARY_DIRECTORY = '.lemonade-publication';

    /**
     * @var Closure(string, string): bool
     */
    private Closure $hardLink;

    private ChunkUploadConfig $cleanupConfig;

    /**
     * Retains filesystem and path-policy collaborators used for constrained publication.
     *
     * Omitting the path policy is retained only for constructor compatibility; such an instance refuses storage.
     */
    public function __construct(
        private TranslatorInterface $translator,
        private Filesystem $filesystem,
        private ?UploadPathPolicy $pathPolicy = null,
        ?Closure $hardLink = null,
        ?ChunkUploadConfig $cleanupConfig = null,
    ) {
        $this->hardLink = $hardLink ?? static fn(string $source, string $target): bool => @link($source, $target);
        $this->cleanupConfig = $cleanupConfig ?? new ChunkUploadConfig();
    }

    /**
     * Creates a visibility-constrained target and verifies containment before and after filesystem mutation.
     */
    public function ensureTargetDirectory(
        string $targetDirectory,
        UploadVisibility $visibility = UploadVisibility::Public,
    ): string {
        $targetDir = rtrim($targetDirectory, '/\\');

        if ($targetDir === '') {
            throw new UploadStorageException($this->translator->get('upload.target_directory_missing'));
        }

        try {
            if (!$this->pathPolicy instanceof UploadPathPolicy) {
                throw new \LogicException('Upload path policy is required for storage.');
            }

            if ($visibility === UploadVisibility::Private) {
                $this->pathPolicy->assertStorageRootsSeparated();
            }

            $mode = $visibility === UploadVisibility::Public ? 0755 : 0700;
            $root = $this->pathPolicy->root($visibility);

            $this->pathPolicy->assertTargetDirectory($targetDir, $visibility);
            $this->filesystem->create($root, $mode);
            $this->secureDirectoryTree($root, $root, $mode);
            $this->pathPolicy->assertTargetDirectory($targetDir, $visibility);
            $this->filesystem->create($targetDir, $mode);
            $this->secureDirectoryTree($root, $targetDir, $mode);

            $this->pathPolicy->assertTargetDirectory($targetDir, $visibility);
        } catch (\Throwable) {
            throw new UploadStorageException($this->translator->get(
                'upload.create_target_directory_failed',
                ['directory' => $targetDir],
            ));
        }

        return $targetDir;
    }

    /**
     * Combines a storage-relative directory and generated filename without interpreting either as a public URL.
     *
     * @throws UploadStorageException When either path component is unsafe
     */
    public function buildRelativePath(string $targetRelativeDirectory, string $storedFilename): string
    {
        try {
            $targetRelativeDirectory = UploadPathPolicy::normalizeRelativeDirectory($targetRelativeDirectory);

            if (
                $storedFilename === ''
                || basename($storedFilename) !== $storedFilename
                || str_contains($storedFilename, "\0")
                || str_contains($storedFilename, '\\')
                || preg_match('/[\x00-\x1F\x7F]/', $storedFilename) === 1
            ) {
                throw new \InvalidArgumentException('Stored upload filename must be a safe basename.');
            }
        } catch (\InvalidArgumentException) {
            throw new UploadStorageException($this->translator->get('upload.target_directory_missing'));
        }

        return $targetRelativeDirectory . '/' . $storedFilename;
    }

    /**
     * Produces an unpredictable basename, or validates a server-owned retry token, while preserving the extension.
     */
    public function generateFilename(?string $extension = null, ?string $publicationToken = null): string
    {
        $extension = $extension !== null ? strtolower(ltrim($extension, '.')) : '';

        if ($publicationToken !== null) {
            if (preg_match('/^(?:[a-f0-9]{32}|[a-f0-9]{64})$/', $publicationToken) !== 1) {
                throw new UploadStorageException($this->translator->get('upload.filename_generation_failed'));
            }

            $filename = $publicationToken;
        } else {
            try {
                $filename = bin2hex(random_bytes(16));
            } catch (\Throwable) {
                throw new UploadStorageException($this->translator->get('upload.filename_generation_failed'));
            }
        }

        return $filename . ($extension !== '' ? '.' . $extension : '');
    }

    /**
     * Combines a validated target directory and generated filename into its publication path.
     *
     * @throws UploadStorageException When the filename is not a safe basename
     */
    public function buildPath(string $targetDirectory, string $storedFilename): string
    {
        if (
            $storedFilename === ''
            || basename($storedFilename) !== $storedFilename
            || str_contains($storedFilename, "\0")
            || str_contains($storedFilename, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $storedFilename) === 1
        ) {
            throw new UploadStorageException($this->translator->get('upload.filename_generation_failed'));
        }

        return rtrim($targetDirectory, '/\\') . DIRECTORY_SEPARATOR . $storedFilename;
    }

    /**
     * Atomically publishes a verified snapshot without exposing partial bytes or replacing an existing target.
     *
     * A complete same-directory temporary file is linked to the final random name only after its measured size and
     * digest match the sealed staging identity. An explicitly idempotent retry may reuse only byte-identical output;
     * any failure after a new publication removes the new target.
     *
     * @throws UploadStorageException When the snapshot changes, publication collides, or rollback-safe IO fails
     */
    public function publishSnapshot(
        StagedUpload $upload,
        string $targetDirectory,
        string $storedFilename,
        UploadVisibility $visibility,
        bool $allowExistingIdentical = false,
    ): int {
        $this->assertReadyTarget($targetDirectory, $visibility);
        $this->cleanupPublicationOrphans($targetDirectory, $visibility);

        $storedPath = $this->buildPath($targetDirectory, $storedFilename);
        $temporaryDirectory = $this->ensurePublicationTemporaryDirectory($targetDirectory, $visibility);
        $temporaryPath = null;
        $sourceHandle = null;
        $temporaryHandle = null;
        $published = false;

        try {
            if (file_exists($storedPath) || is_link($storedPath)) {
                if ($allowExistingIdentical && $this->existingFileMatchesSnapshot($storedPath, $upload)) {
                    return $upload->sizeBytes();
                }

                throw new UploadStorageException($this->translator->get('upload.move_failed'));
            }

            [$temporaryPath, $temporaryHandle] = $this->createPublicationFile($temporaryDirectory);
            $sourceHandle = @fopen($upload->path(), 'rb');

            if ($sourceHandle === false) {
                throw new UploadStorageException($this->translator->get('upload.tmp_not_valid'));
            }

            $hash = hash_init('sha256');
            $written = $this->copySnapshot(
                $sourceHandle,
                $temporaryHandle,
                $hash,
            );
            $digest = hash_final($hash);

            if ($written !== $upload->sizeBytes() || !hash_equals($upload->sha256(), $digest)) {
                throw new UploadStorageException($this->translator->get('upload.tmp_not_valid'));
            }

            $this->flushPublication($temporaryHandle);
            $this->close($sourceHandle, $upload->path());
            $sourceHandle = null;

            $mode = $visibility === UploadVisibility::Public ? 0644 : 0600;

            if (!@chmod($temporaryPath, $mode)) {
                throw new UploadStorageException($this->translator->get('upload.move_failed'));
            }

            $this->assertReadyTarget($targetDirectory, $visibility);

            if (!(($this->hardLink)($temporaryPath, $storedPath))) {
                throw new UploadStorageException($this->translator->get('upload.move_failed'));
            }

            $published = true;
            $this->assertHardLinkIdentity($temporaryPath, $storedPath);
            $this->assertPublishedIdentity($storedPath, $upload);

            if (!@unlink($temporaryPath)) {
                throw new UploadStorageException($this->translator->get('upload.move_failed'));
            }

            $temporaryPath = null;
            $this->close($temporaryHandle, $storedPath);
            $temporaryHandle = null;

            return $written;
        } catch (\Throwable $exception) {
            $this->closeQuietly($sourceHandle);
            $this->closeQuietly($temporaryHandle);
            $rollbackFailure = null;

            if ($published) {
                try {
                    $this->removePublishedPath($storedPath);
                } catch (UploadStorageException $rollbackException) {
                    $rollbackFailure = $rollbackException;
                }
            }

            if ($temporaryPath !== null) {
                $this->removeQuietly($temporaryPath);
            }

            if ($rollbackFailure instanceof UploadStorageException) {
                throw new UploadStorageException(
                    $this->translator->get('upload.move_failed'),
                    previous: $rollbackFailure,
                );
            }

            if ($exception instanceof UploadStorageException) {
                throw $exception;
            }

            throw new UploadStorageException(
                $this->translator->get('upload.move_failed'),
                previous: $exception,
            );
        }
    }

    /**
     * Reports the persisted byte count or maps filesystem failures to the upload storage category.
     */
    public function fileSize(string $path): int
    {
        try {
            $size = $this->filesystem->size($path);
        } catch (\Throwable) {
            throw new UploadStorageException($this->translator->get('upload.file_size_not_detected'));
        }

        return $size;
    }

    /**
     * Removes one just-published result when its owning higher-level transaction cannot be confirmed.
     *
     * The path is revalidated against its visibility root and symbolic links are never followed.
     *
     * @throws UploadStorageException When the result no longer identifies a safely removable regular file
     */
    public function rollbackPublished(UploadedFile $upload): void
    {
        $directory = dirname($upload->storedPath());
        $expectedPath = $this->buildPath($directory, $upload->storedFilename());

        if ($expectedPath !== $upload->storedPath()) {
            throw new UploadStorageException($this->translator->get('upload.move_failed'));
        }

        $this->assertReadyTarget($directory, $upload->visibility());

        if (!is_file($expectedPath) || is_link($expectedPath)) {
            throw new UploadStorageException($this->translator->get('upload.move_failed'));
        }

        $this->removePublishedPath($expectedPath);
    }

    /**
     * Reclaims a bounded number of old publication temporaries whose process lease is no longer active.
     *
     * Only exact framework-owned temporary basenames inside the validated target directory are considered. Symbolic
     * links, recent files, changed path identities, and files locked by an active publisher are skipped.
     */
    public function cleanupPublicationOrphans(
        string $targetDirectory,
        UploadVisibility $visibility,
        ?int $now = null,
    ): int {
        $targetDirectory = rtrim($targetDirectory, '/\\');
        $this->assertReadyTarget($targetDirectory, $visibility);

        if (!is_dir($targetDirectory) || is_link($targetDirectory)) {
            return 0;
        }

        $temporaryDirectory = $this->publicationTemporaryDirectory($targetDirectory);

        if (!is_dir($temporaryDirectory) || is_link($temporaryDirectory)) {
            return 0;
        }

        try {
            $this->assertPublicationTemporaryDirectory($temporaryDirectory, $visibility);
        } catch (\Throwable $exception) {
            throw new UploadStorageException(
                $this->translator->get('upload.move_failed'),
                previous: $exception,
            );
        }

        $now ??= time();
        $cutoff = $now - $this->cleanupConfig->stagingOrphanTtlSeconds();
        $removed = 0;
        $examined = 0;

        try {
            foreach ($this->filesystem->tree($temporaryDirectory, false) as $entry) {
                if (++$examined > $this->cleanupConfig->cleanupBatchSize()) {
                    break;
                }

                $path = $entry->getPathname();

                if (!$this->isPublicationOrphanCandidate($path, $temporaryDirectory, $cutoff)) {
                    continue;
                }

                $handle = @fopen($path, 'rb');

                if ($handle === false) {
                    continue;
                }

                try {
                    if (!@flock($handle, LOCK_EX | LOCK_NB)) {
                        continue;
                    }

                    if (
                        !$this->openedFileMatchesPath($handle, $path)
                        || !$this->isPublicationOrphanCandidate($path, $temporaryDirectory, $cutoff)
                    ) {
                        continue;
                    }

                    if (@unlink($path)) {
                        ++$removed;
                    }
                } finally {
                    @flock($handle, LOCK_UN);
                    @fclose($handle);
                }
            }
        } catch (\Throwable $exception) {
            throw new UploadStorageException(
                $this->translator->get('upload.move_failed'),
                previous: $exception,
            );
        }

        return $removed;
    }

    private function assertReadyTarget(string $targetDirectory, UploadVisibility $visibility): void
    {
        if (!$this->pathPolicy instanceof UploadPathPolicy) {
            throw new UploadStorageException($this->translator->get('upload.target_directory_missing'));
        }

        try {
            $this->pathPolicy->assertTargetDirectory($targetDirectory, $visibility);
        } catch (\Throwable $exception) {
            throw new UploadStorageException(
                $this->translator->get('upload.target_directory_missing'),
                previous: $exception,
            );
        }
    }

    /**
     * @return array{string, resource}
     */
    private function createPublicationFile(string $temporaryDirectory): array
    {
        $previous = null;

        for ($attempt = 0; $attempt < self::CREATE_ATTEMPTS; ++$attempt) {
            try {
                $path = $temporaryDirectory
                    . DIRECTORY_SEPARATOR
                    . '.lemonade-publish-' . bin2hex(random_bytes(16)) . '.tmp';
                $handle = @fopen($path, 'x+b');
            } catch (\Throwable $exception) {
                $previous = $exception;

                continue;
            }

            if ($handle !== false) {
                if (!@chmod($path, 0600)) {
                    $this->closeQuietly($handle);
                    $this->removeQuietly($path);

                    continue;
                }

                if (!@flock($handle, LOCK_SH)) {
                    $this->closeQuietly($handle);
                    $this->removeQuietly($path);

                    continue;
                }

                return [$path, $handle];
            }
        }

        throw new UploadStorageException(
            $this->translator->get('upload.move_failed'),
            previous: $previous,
        );
    }

    /**
     * @param resource $source
     * @param resource $target
     */
    private function copySnapshot($source, $target, \HashContext $hash): int
    {
        $written = 0;

        while (!feof($source)) {
            $chunk = @fread($source, self::COPY_BYTES);

            if ($chunk === false) {
                throw new UploadStorageException($this->translator->get('upload.move_failed'));
            }

            if ($chunk === '') {
                if (feof($source)) {
                    break;
                }

                throw new UploadStorageException($this->translator->get('upload.move_failed'));
            }

            $this->writeAll($target, $chunk);
            hash_update($hash, $chunk);
            $written += strlen($chunk);
        }

        return $written;
    }

    /**
     * @param resource $handle
     */
    private function writeAll($handle, string $contents): void
    {
        $length = strlen($contents);
        $offset = 0;

        while ($offset < $length) {
            $written = @fwrite($handle, substr($contents, $offset));

            if ($written === false || $written === 0) {
                throw new UploadStorageException($this->translator->get('upload.move_failed'));
            }

            $offset += $written;
        }
    }

    /**
     * @param resource $handle
     */
    private function flushPublication($handle): void
    {
        if (!@fflush($handle)) {
            throw new UploadStorageException($this->translator->get('upload.move_failed'));
        }

        if (function_exists('fsync') && !@fsync($handle)) {
            throw new UploadStorageException($this->translator->get('upload.move_failed'));
        }
    }

    /**
     * @param resource $handle
     */
    private function close($handle, string $path): void
    {
        if (!@fclose($handle)) {
            throw new UploadStorageException(sprintf('Upload file "%s" cannot be closed.', $path));
        }
    }

    private function assertPublishedIdentity(string $storedPath, StagedUpload $upload): void
    {
        if (!is_file($storedPath) || is_link($storedPath)) {
            throw new UploadStorageException($this->translator->get('upload.move_failed'));
        }

        try {
            $size = $this->filesystem->size($storedPath);
            $hash = $this->filesystem->hash($storedPath, 'sha256');
        } catch (\Throwable $exception) {
            throw new UploadStorageException(
                $this->translator->get('upload.move_failed'),
                previous: $exception,
            );
        }

        if ($size !== $upload->sizeBytes() || !hash_equals($upload->sha256(), $hash)) {
            throw new UploadStorageException($this->translator->get('upload.move_failed'));
        }
    }

    private function assertHardLinkIdentity(string $temporaryPath, string $storedPath): void
    {
        $temporary = @lstat($temporaryPath);
        $published = @lstat($storedPath);

        if (
            $temporary === false
            || $published === false
            || $temporary['dev'] !== $published['dev']
            || $temporary['ino'] !== $published['ino']
            || $published['nlink'] < 2
        ) {
            throw new UploadStorageException($this->translator->get('upload.move_failed'));
        }
    }

    private function existingFileMatchesSnapshot(string $storedPath, StagedUpload $upload): bool
    {
        if (!is_file($storedPath) || is_link($storedPath)) {
            return false;
        }

        try {
            $size = $this->filesystem->size($storedPath);
            $hash = $this->filesystem->hash($storedPath, 'sha256');
        } catch (\Throwable) {
            return false;
        }

        return $size === $upload->sizeBytes()
            && hash_equals($upload->sha256(), $hash);
    }

    private function secureDirectoryTree(string $root, string $target, int $mode): void
    {
        $resolvedRoot = realpath($root);
        $resolvedTarget = realpath($target);

        if (!is_string($resolvedRoot) || !is_string($resolvedTarget)) {
            throw new \RuntimeException('Upload directory identity cannot be resolved.');
        }

        $directories = [];
        $cursor = $resolvedTarget;

        while (true) {
            array_unshift($directories, $cursor);

            if ($cursor === $resolvedRoot) {
                break;
            }

            $parent = dirname($cursor);

            if ($parent === $cursor) {
                throw new \RuntimeException('Upload directory escapes its managed root.');
            }

            $cursor = $parent;
        }

        foreach ($directories as $directory) {
            if (!is_dir($directory) || is_link($directory) || !@chmod($directory, $mode)) {
                throw new \RuntimeException('Upload directory permissions cannot be restricted.');
            }

            clearstatcache(true, $directory);
            $permissions = @fileperms($directory);

            if (!is_int($permissions) || ($permissions & 0777) !== $mode) {
                throw new \RuntimeException('Upload directory permissions remain unsafe.');
            }
        }
    }

    private function ensurePublicationTemporaryDirectory(
        string $targetDirectory,
        UploadVisibility $visibility,
    ): string {
        $directory = $this->publicationTemporaryDirectory($targetDirectory);

        try {
            if (is_link($directory)) {
                throw new \RuntimeException('Upload publication temporary directory cannot be a symbolic link.');
            }

            $this->filesystem->create($directory, 0700);

            if (!@chmod($directory, 0700)) {
                throw new \RuntimeException('Upload publication temporary permissions cannot be restricted.');
            }

            $this->assertPublicationTemporaryDirectory($directory, $visibility);
        } catch (\Throwable $exception) {
            throw new UploadStorageException(
                $this->translator->get('upload.create_target_directory_failed', ['directory' => $directory]),
                previous: $exception,
            );
        }

        return $directory;
    }

    private function assertPublicationTemporaryDirectory(
        string $directory,
        UploadVisibility $visibility,
    ): void {
        if (!$this->pathPolicy instanceof UploadPathPolicy) {
            throw new \LogicException('Upload path policy is required for storage.');
        }

        $this->pathPolicy->assertTargetDirectory($directory, $visibility);

        if (!is_dir($directory) || is_link($directory)) {
            throw new \RuntimeException('Upload publication temporary directory is unsafe.');
        }

        clearstatcache(true, $directory);
        $permissions = @fileperms($directory);

        if (!is_int($permissions) || ($permissions & 0777) !== 0700) {
            throw new \RuntimeException('Upload publication temporary directory permissions are unsafe.');
        }
    }

    private function publicationTemporaryDirectory(string $targetDirectory): string
    {
        return $targetDirectory . DIRECTORY_SEPARATOR . self::TEMPORARY_DIRECTORY;
    }

    /** @phpstan-impure */
    private function isPublicationOrphanCandidate(string $path, string $temporaryDirectory, int $cutoff): bool
    {
        clearstatcache(true, $path);

        if (
            dirname($path) !== $temporaryDirectory
            || preg_match('/^\.lemonade-publish-[a-f0-9]{32}\.tmp$/', basename($path)) !== 1
            || !is_file($path)
            || is_link($path)
        ) {
            return false;
        }

        $modified = @filemtime($path);

        return is_int($modified) && $modified <= $cutoff;
    }

    /**
     * @param resource $handle
     */
    private function openedFileMatchesPath($handle, string $path): bool
    {
        $opened = @fstat($handle);
        $current = @lstat($path);

        return $opened !== false
            && $current !== false
            && !is_link($path)
            && ($opened['mode'] & 0170000) === 0100000
            && $opened['dev'] === $current['dev']
            && $opened['ino'] === $current['ino'];
    }

    /**
     * @param resource|false|null $handle
     */
    private function closeQuietly($handle): void
    {
        if (!is_resource($handle)) {
            return;
        }

        try {
            @fclose($handle);
        } catch (\Throwable) {
        }
    }

    private function removeQuietly(string $path): void
    {
        if (!is_file($path) && !is_link($path)) {
            return;
        }

        try {
            @unlink($path);
        } catch (\Throwable) {
        }
    }

    private function removePublishedPath(string $path): void
    {
        try {
            $removed = @unlink($path);
        } catch (\Throwable $exception) {
            throw new UploadStorageException(
                $this->translator->get('upload.move_failed'),
                previous: $exception,
            );
        }

        if (!$removed || is_file($path) || is_link($path)) {
            throw new UploadStorageException($this->translator->get('upload.move_failed'));
        }
    }
}
