<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload\Storage;

use Closure;
use Lemonade\Framework\Filesystem\Filesystem;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Upload\Exception\UploadStorageException;
use Lemonade\Framework\Upload\Staging\StagedUpload;
use Lemonade\Framework\Upload\UploadPathPolicy;
use Lemonade\Framework\Upload\ValueObject\UploadedFile;

/**
 * Publishes caller-asserted snapshots inside private upload storage.
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

    /**
     * Retains filesystem and path-policy collaborators used for constrained publication.
     */
    public function __construct(
        private TranslatorInterface $translator,
        private Filesystem $filesystem,
        private UploadPathPolicy $pathPolicy,
        ?Closure $hardLink = null,
    ) {
        $this->hardLink = $hardLink ?? static fn(string $source, string $target): bool => @link($source, $target);
    }

    /**
     * Validates the upload target and creates missing directories without changing existing permissions.
     */
    public function ensureTargetDirectory(
        string $targetDirectory,
    ): string {
        $targetDir = rtrim($targetDirectory, '/\\');

        if ($targetDir === '') {
            throw new UploadStorageException($this->translator->get('upload.target_directory_missing'));
        }

        try {
            $this->pathPolicy->assertTargetDirectory($targetDir);
            $this->filesystem->create($targetDir, 0700);
        } catch (\Throwable $exception) {
            throw new UploadStorageException($this->translator->get(
                'upload.create_target_directory_failed',
                ['directory' => $targetDir],
            ), previous: $exception);
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
        bool $allowExistingIdentical = false,
    ): int {
        $this->assertReadyTarget($targetDirectory);

        $storedPath = $this->buildPath($targetDirectory, $storedFilename);
        $temporaryDirectory = $this->ensurePublicationTemporaryDirectory($targetDirectory);
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
            $stat = fstat($temporaryHandle);

            if ($stat === false || $stat['size'] !== $written) {
                throw new UploadStorageException($this->translator->get('upload.move_failed'));
            }

            $this->close($sourceHandle, $upload->path());
            $sourceHandle = null;

            if (!(($this->hardLink)($temporaryPath, $storedPath))) {
                throw new UploadStorageException($this->translator->get('upload.move_failed'));
            }

            $published = true;

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
     * Removes one just-published result when its owning higher-level transaction cannot be confirmed.
     *
     * The path is revalidated against the private upload root and symbolic links are never followed.
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

        $this->assertReadyTarget($directory);

        if (!is_file($expectedPath) || is_link($expectedPath)) {
            throw new UploadStorageException($this->translator->get('upload.move_failed'));
        }

        $this->removePublishedPath($expectedPath);
    }

    private function assertReadyTarget(string $targetDirectory): void
    {
        try {
            $this->pathPolicy->assertTargetDirectory($targetDirectory);
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

    private function ensurePublicationTemporaryDirectory(
        string $targetDirectory,
    ): string {
        $directory = $targetDirectory . DIRECTORY_SEPARATOR . self::TEMPORARY_DIRECTORY;

        try {
            $this->filesystem->create($directory, 0700);
        } catch (\Throwable $exception) {
            throw new UploadStorageException(
                $this->translator->get('upload.create_target_directory_failed', ['directory' => $directory]),
                previous: $exception,
            );
        }

        return $directory;
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
