<?php

declare(strict_types=1);

namespace Lemonade\Framework\Filesystem\Manager;

use Exception;
use FilesystemIterator;
use Generator;
use GlobIterator;
use Lemonade\Framework\Filesystem\Contract\DirectoryManagerInterface;
use Lemonade\Framework\Filesystem\Exception\FilesystemException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

use function array_pop;
use function array_unshift;
use function basename;
use function bin2hex;
use function chmod;
use function decoct;
use function dirname;
use function fclose;
use function fflush;
use function file_exists;
use function fileperms;
use function fopen;
use function fwrite;
use function getcwd;
use function implode;
use function is_dir;
use function is_file;
use function is_link;
use function is_resource;
use function lstat;
use function mkdir;
use function preg_match;
use function preg_replace;
use function random_bytes;
use function realpath;
use function rename;
use function rmdir;
use function sprintf;
use function stat;
use function str_contains;
use function str_starts_with;
use function stream_copy_to_stream;
use function stream_is_local;
use function strlen;
use function strtolower;
use function substr;
use function unlink;

/**
 * Performs local directory and file-tree operations while translating native failures to filesystem contracts.
 */
final class DirectoryManager implements DirectoryManagerInterface
{
    /**
     * {@inheritdoc}
     */
    public function create(string $path, int $mode = 0775): void
    {
        if (is_dir($path)) {
            return;
        }

        try {
            $created = @mkdir($path, $mode, true);
        } catch (Exception $exception) {
            if (is_dir($path)) {
                return;
            }

            throw new FilesystemException(
                sprintf("Unable to create directory '%s' with mode %s.", $path, decoct($mode)),
                FilesystemException::CODE_DIR_CREATE_FAILED,
                $exception,
            );
        }

        if (!$created && !is_dir($path)) {
            throw new FilesystemException(
                sprintf("Unable to create directory '%s' with mode %s.", $path, decoct($mode)),
                FilesystemException::CODE_DIR_CREATE_FAILED,
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function copy(string $src, string $dst, bool $overwrite = false): void
    {
        $sourceIsLocal = stream_is_local($src);
        $sourceCanCompare = $sourceIsLocal
            && (!str_contains($src, '://') || str_starts_with($src, 'file://'));

        if ($sourceCanCompare && !$this->pathExists($src)) {
            throw new FilesystemException(
                "File or directory '{$src}' not found.",
                FilesystemException::CODE_PATH_NOT_FOUND,
            );
        }

        if ($sourceCanCompare && is_link($src)) {
            throw new FilesystemException(
                "Symbolic link source '{$src}' cannot be copied.",
                FilesystemException::CODE_COPY_FAILED,
            );
        }

        if (!stream_is_local($dst)) {
            throw new FilesystemException(
                "Copy destination '{$dst}' is not on the local filesystem.",
                FilesystemException::CODE_COPY_FAILED,
            );
        }

        $sourceIsDirectory = $sourceCanCompare && is_dir($src);
        $this->assertDistinctCopyPaths($src, $dst, $sourceIsDirectory, $sourceCanCompare);

        if (is_link($dst)) {
            throw new FilesystemException(
                "Copy destination '{$dst}' is a symbolic link.",
                FilesystemException::CODE_FILE_CONFLICT,
            );
        }

        if (!$overwrite && $this->pathExists($dst)) {
            throw new FilesystemException(
                "File or directory '{$dst}' already exists.",
                FilesystemException::CODE_FILE_CONFLICT,
            );
        }

        if ($sourceIsDirectory) {
            if ($this->pathExists($dst) && !is_dir($dst)) {
                throw new FilesystemException(
                    "Directory copy destination '{$dst}' is not a directory.",
                    FilesystemException::CODE_FILE_CONFLICT,
                );
            }

            $this->copyDirectory($src, $dst, $overwrite);

            return;
        }

        if ($this->pathExists($dst) && is_dir($dst)) {
            throw new FilesystemException(
                "File copy destination '{$dst}' is a directory.",
                FilesystemException::CODE_FILE_CONFLICT,
            );
        }

        $this->copyFile($src, $dst, $overwrite);
    }

    private function copyDirectory(string $src, string $dst, bool $overwrite): void
    {
        $destinationCreated = false;

        try {
            $destinationCreated = $this->createCopyDestination($dst);

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $src,
                    FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO,
                ),
                RecursiveIteratorIterator::SELF_FIRST,
            );

            foreach ($iterator as $item) {
                if (!$item instanceof SplFileInfo) {
                    continue;
                }

                if ($item->isLink()) {
                    throw new FilesystemException(
                        "Symbolic link '{$item->getPathname()}' cannot be copied.",
                        FilesystemException::CODE_COPY_FAILED,
                    );
                }

                $innerIterator = $iterator->getInnerIterator();

                if (!$innerIterator instanceof RecursiveDirectoryIterator) {
                    throw new FilesystemException(
                        "Unable to resolve relative path while copying directory '{$src}'.",
                        FilesystemException::CODE_COPY_FAILED,
                    );
                }

                $relativePath = $innerIterator->getSubPathname();
                $target = $dst . DIRECTORY_SEPARATOR . $relativePath;

                if ($item->isDir()) {
                    $this->create($target);

                    continue;
                }

                $this->copyFile($item->getPathname(), $target, $overwrite);
            }
        } catch (FilesystemException $exception) {
            $this->cleanupFailedDirectoryCopy($dst, $destinationCreated);

            throw $exception;
        } catch (Exception $exception) {
            $this->cleanupFailedDirectoryCopy($dst, $destinationCreated);

            throw new FilesystemException(
                "Unable to copy directory '{$src}' to '{$dst}'.",
                FilesystemException::CODE_COPY_FAILED,
                $exception,
            );
        }
    }

    private function copyFile(string $src, string $dst, bool $overwrite): void
    {
        $this->create(dirname($dst));

        $temporary = null;
        $sourceHandle = null;
        $temporaryHandle = null;

        try {
            [$temporary, $temporaryHandle] = $this->createTemporaryFile(
                $dst,
                FilesystemException::CODE_COPY_FAILED,
            );

            $openedSource = @fopen($src, 'rb');
            if ($openedSource === false) {
                throw new FilesystemException(
                    "Unable to open source file '{$src}' for reading.",
                    FilesystemException::CODE_COPY_FAILED,
                );
            }
            $sourceHandle = $openedSource;

            if (@stream_copy_to_stream($sourceHandle, $temporaryHandle) === false) {
                throw new FilesystemException(
                    "Unable to copy file '{$src}' to '{$dst}'.",
                    FilesystemException::CODE_COPY_FAILED,
                );
            }

            if (!@fflush($temporaryHandle)) {
                throw new FilesystemException(
                    "Unable to flush copied file '{$dst}'.",
                    FilesystemException::CODE_COPY_FAILED,
                );
            }

            $this->closeHandle($temporaryHandle, $dst, FilesystemException::CODE_COPY_FAILED);
            $temporaryHandle = null;
            $this->closeHandle($sourceHandle, $src, FilesystemException::CODE_COPY_FAILED);
            $sourceHandle = null;

            $this->publishTemporaryFile(
                $temporary,
                $dst,
                $overwrite,
                FilesystemException::CODE_COPY_FAILED,
            );
            $temporary = null;
        } catch (FilesystemException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            throw new FilesystemException(
                "Unable to copy file '{$src}' to '{$dst}'.",
                FilesystemException::CODE_COPY_FAILED,
                $exception,
            );
        } finally {
            $this->closeHandleQuietly($sourceHandle);
            $this->closeHandleQuietly($temporaryHandle);
            $this->removeTemporaryFile($temporary);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $file, string $data, ?int $mode = 0666): void
    {
        if (!stream_is_local($file)) {
            throw new FilesystemException(
                "File '{$file}' is not on the local filesystem.",
                FilesystemException::CODE_FILE_WRITE_FAILED,
            );
        }

        $this->create(dirname($file));
        $publicationMode = $this->publicationMode($file, $mode);

        $temporary = null;
        $handle = null;

        try {
            [$temporary, $handle] = $this->createTemporaryFile(
                $file,
                FilesystemException::CODE_FILE_WRITE_FAILED,
            );

            $this->writeAll($handle, $data, $file);

            if (!@fflush($handle)) {
                throw new FilesystemException(
                    "Unable to flush file '{$file}'.",
                    FilesystemException::CODE_FILE_WRITE_FAILED,
                );
            }

            if ($publicationMode !== null && !@chmod($temporary, $publicationMode)) {
                throw new FilesystemException(
                    "Unable to chmod file '{$file}' to mode " . decoct($publicationMode) . '.',
                    FilesystemException::CODE_FILE_WRITE_FAILED,
                );
            }

            $this->closeHandle($handle, $file, FilesystemException::CODE_FILE_WRITE_FAILED);
            $handle = null;

            $this->publishTemporaryFile(
                $temporary,
                $file,
                true,
                FilesystemException::CODE_FILE_WRITE_FAILED,
            );
            $temporary = null;
        } catch (FilesystemException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            throw new FilesystemException(
                "Unable to write file '{$file}'.",
                FilesystemException::CODE_FILE_WRITE_FAILED,
                $exception,
            );
        } finally {
            $this->closeHandleQuietly($handle);
            $this->removeTemporaryFile($temporary);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function move(string $source, string $target): void
    {
        if (!stream_is_local($source) || !stream_is_local($target)) {
            throw new FilesystemException(
                "Files '{$source}' and '{$target}' must be on the local filesystem.",
                FilesystemException::CODE_FILE_MOVE_FAILED,
            );
        }

        if (!$this->pathExists($source)) {
            throw new FilesystemException(
                "File '{$source}' not found.",
                FilesystemException::CODE_FILE_NOT_FOUND,
            );
        }

        try {
            $sourceStat = @lstat($source);
            $targetDirectoryStat = @stat(dirname($target));

            if ($sourceStat === false || $targetDirectoryStat === false) {
                throw new FilesystemException(
                    "Unable to verify filesystem boundaries for '{$source}' and '{$target}'.",
                    FilesystemException::CODE_FILE_MOVE_FAILED,
                );
            }

            if ($sourceStat['dev'] !== $targetDirectoryStat['dev']) {
                throw new FilesystemException(
                    "Unable to atomically move '{$source}' to '{$target}' across filesystems.",
                    FilesystemException::CODE_FILE_MOVE_FAILED,
                );
            }

            if (!@rename($source, $target)) {
                throw new FilesystemException(
                    "Unable to move file '{$source}' to '{$target}'.",
                    FilesystemException::CODE_FILE_MOVE_FAILED,
                );
            }
        } catch (FilesystemException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            throw new FilesystemException(
                "Unable to move file '{$source}' to '{$target}'.",
                FilesystemException::CODE_FILE_MOVE_FAILED,
                $exception,
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $path): void
    {
        if (is_file($path) || is_link($path)) {
            try {
                $deleted = DIRECTORY_SEPARATOR === '\\' && is_dir($path)
                    ? @rmdir($path)
                    : @unlink($path);
            } catch (Exception $exception) {
                throw new FilesystemException(
                    "Unable to delete file or symbolic link '{$path}'.",
                    FilesystemException::CODE_FILE_DELETE_FAILED,
                    $exception,
                );
            }

            if (!$deleted) {
                throw new FilesystemException(
                    "Unable to delete file or symbolic link '{$path}'.",
                    FilesystemException::CODE_FILE_DELETE_FAILED,
                );
            }

            return;
        }

        if (!is_dir($path)) {
            return;
        }

        try {
            foreach (new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS) as $item) {
                if ($item instanceof SplFileInfo) {
                    $this->delete($item->getPathname());
                }
            }

            if (!@rmdir($path)) {
                throw new FilesystemException(
                    "Unable to delete directory '{$path}'.",
                    FilesystemException::CODE_DIR_DELETE_FAILED,
                );
            }
        } catch (FilesystemException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            throw new FilesystemException(
                "Unable to delete directory '{$path}'.",
                FilesystemException::CODE_DIR_DELETE_FAILED,
                $exception,
            );
        }
    }

    /**
     * Lazily yields entries without following linked directories.
     *
     * @return Generator<string, string>
     * @throws FilesystemException
     */
    public function stream(string $path, bool $recursive = true): Generator
    {
        $basePath = $this->resolveDirectoryPath($path);

        try {
            $iterator = $recursive
                ? new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($basePath, FilesystemIterator::SKIP_DOTS),
                )
                : new FilesystemIterator($basePath, FilesystemIterator::SKIP_DOTS);

            foreach ($iterator as $item) {
                if ($item instanceof SplFileInfo) {
                    yield $item->getFilename() => $item->getPathname();
                }
            }
        } catch (FilesystemException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            throw new FilesystemException(
                "Failed to stream directory '{$path}'.",
                FilesystemException::CODE_FILE_READ_FAILED,
                $exception,
            );
        }
    }

    /**
     * Lazily yields filesystem metadata without following linked directories.
     *
     * @return Generator<int, SplFileInfo>
     * @throws FilesystemException
     */
    public function tree(string $path, bool $recursive = true): Generator
    {
        $basePath = $this->resolveDirectoryPath($path);

        try {
            $base = new RecursiveDirectoryIterator(
                $basePath,
                FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO,
            );

            if (!$recursive) {
                foreach ($base as $item) {
                    if ($item instanceof SplFileInfo) {
                        yield $item;
                    }
                }

                return;
            }

            $iterator = new RecursiveIteratorIterator($base, RecursiveIteratorIterator::SELF_FIRST);
            foreach ($iterator as $item) {
                if ($item instanceof SplFileInfo) {
                    yield $item;
                }
            }
        } catch (FilesystemException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            throw new FilesystemException(
                "Failed to walk directory '{$path}'.",
                FilesystemException::CODE_FILE_READ_FAILED,
                $exception,
            );
        }
    }

    /**
     * Lazily yields direct matches without allowing the pattern to leave the base directory.
     *
     * @return Generator<int, string>
     * @throws FilesystemException
     */
    public function find(string $pattern, string $path): Generator
    {
        if (
            $pattern === '.'
            || $pattern === '..'
            || str_contains($pattern, "\0")
            || str_contains($pattern, '/')
            || str_contains($pattern, '\\')
        ) {
            throw new FilesystemException(
                "Glob pattern '{$pattern}' must address direct entries only.",
                FilesystemException::CODE_FIND_PATTERN_INVALID,
            );
        }

        $basePath = $this->resolveDirectoryPath($path);
        $fullPattern = $basePath . DIRECTORY_SEPARATOR . $pattern;

        try {
            $iterator = new GlobIterator($fullPattern, FilesystemIterator::SKIP_DOTS);

            foreach ($iterator as $fileInfo) {
                if ($fileInfo instanceof SplFileInfo) {
                    yield $fileInfo->getPathname();
                }
            }
        } catch (FilesystemException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            throw new FilesystemException(
                "Failed to search directory '{$path}'.",
                FilesystemException::CODE_FILE_READ_FAILED,
                $exception,
            );
        }
    }

    private function assertDistinctCopyPaths(
        string $source,
        string $destination,
        bool $sourceIsDirectory,
        bool $sourceCanCompare,
    ): void {
        if (!$sourceCanCompare) {
            return;
        }

        $sourcePath = $this->comparableLocalPath($source);
        $destinationPath = $this->comparableLocalPath($destination);

        if ($sourcePath === $destinationPath) {
            throw new FilesystemException(
                "Copy source '{$source}' and destination '{$destination}' resolve to the same path.",
                FilesystemException::CODE_FILE_CONFLICT,
            );
        }

        if ($sourceIsDirectory && $this->pathIsInside($destinationPath, $sourcePath)) {
            throw new FilesystemException(
                "Directory '{$source}' cannot be copied into its own descendant '{$destination}'.",
                FilesystemException::CODE_FILE_CONFLICT,
            );
        }
    }

    private function comparableLocalPath(string $path): string
    {
        try {
            $resolved = @realpath($path);
        } catch (Exception $exception) {
            throw new FilesystemException(
                "Unable to resolve filesystem path '{$path}'.",
                FilesystemException::CODE_COPY_FAILED,
                $exception,
            );
        }

        if ($resolved !== false) {
            return $this->normalizeAbsolutePath($resolved);
        }

        $absolutePath = $path;
        if (!$this->pathIsAbsolute($absolutePath)) {
            $workingDirectory = getcwd();
            if ($workingDirectory === false) {
                throw new FilesystemException(
                    "Unable to resolve relative filesystem path '{$path}'.",
                    FilesystemException::CODE_COPY_FAILED,
                );
            }

            $absolutePath = $workingDirectory . DIRECTORY_SEPARATOR . $absolutePath;
        }

        $cursor = $absolutePath;
        $suffix = [];

        while (true) {
            try {
                $resolved = @realpath($cursor);
            } catch (Exception $exception) {
                throw new FilesystemException(
                    "Unable to resolve filesystem path '{$path}'.",
                    FilesystemException::CODE_COPY_FAILED,
                    $exception,
                );
            }

            if ($resolved !== false) {
                break;
            }

            $parent = dirname($cursor);
            if ($parent === $cursor) {
                throw new FilesystemException(
                    "Unable to resolve filesystem path '{$path}'.",
                    FilesystemException::CODE_COPY_FAILED,
                );
            }

            array_unshift($suffix, basename($cursor));
            $cursor = $parent;
        }

        $candidate = $resolved;
        if ($suffix !== []) {
            $candidate .= DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $suffix);
        }

        return $this->normalizeAbsolutePath($candidate);
    }

    private function normalizeAbsolutePath(string $path): string
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $path = preg_replace('~[\\\\/]+~', DIRECTORY_SEPARATOR, $path) ?? $path;
            $prefix = '';

            if (preg_match('/^[A-Za-z]:\\\\/', $path) === 1) {
                $prefix = substr($path, 0, 3);
                $path = substr($path, 3);
            } elseif (str_starts_with($path, DIRECTORY_SEPARATOR)) {
                $prefix = DIRECTORY_SEPARATOR;
                $path = substr($path, 1);
            }
        } else {
            $path = preg_replace('~/+~', DIRECTORY_SEPARATOR, $path) ?? $path;
            $prefix = DIRECTORY_SEPARATOR;
            $path = substr($path, 1);
        }

        $segments = [];
        foreach (explode(DIRECTORY_SEPARATOR, $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        $normalized = $prefix . implode(DIRECTORY_SEPARATOR, $segments);

        return DIRECTORY_SEPARATOR === '\\' ? strtolower($normalized) : $normalized;
    }

    private function pathIsAbsolute(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if ($path[0] === '/' || $path[0] === '\\') {
            return true;
        }

        return preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }

    private function pathIsInside(string $candidate, string $directory): bool
    {
        $prefix = $directory === DIRECTORY_SEPARATOR
            ? $directory
            : $directory . DIRECTORY_SEPARATOR;

        return str_starts_with($candidate, $prefix);
    }

    /**
     * @return array{0: string, 1: resource}
     */
    private function createTemporaryFile(string $destination, int $failureCode): array
    {
        $directory = dirname($destination);
        $previous = null;

        for ($attempt = 0; $attempt < 10; ++$attempt) {
            try {
                $temporary = $directory
                    . DIRECTORY_SEPARATOR
                    . '.lemonade-' . bin2hex(random_bytes(16)) . '.tmp';
                $handle = @fopen($temporary, 'x+b');
            } catch (Exception $exception) {
                $previous = $exception;

                continue;
            }

            if ($handle !== false) {
                return [$temporary, $handle];
            }
        }

        throw new FilesystemException(
            "Unable to create a temporary file beside '{$destination}'.",
            $failureCode,
            $previous,
        );
    }

    /**
     * @param resource $handle
     */
    private function writeAll($handle, string $data, string $file): void
    {
        $length = strlen($data);
        $written = 0;

        while ($written < $length) {
            $result = @fwrite($handle, substr($data, $written));
            if ($result === false || $result === 0) {
                throw new FilesystemException(
                    "Unable to write file '{$file}'.",
                    FilesystemException::CODE_FILE_WRITE_FAILED,
                );
            }

            $written += $result;
        }
    }

    private function publishTemporaryFile(
        string $temporary,
        string $destination,
        bool $overwrite,
        int $failureCode,
    ): void {
        if (!$overwrite && $this->pathExists($destination)) {
            throw new FilesystemException(
                "File '{$destination}' already exists.",
                FilesystemException::CODE_FILE_CONFLICT,
            );
        }

        try {
            $published = @rename($temporary, $destination);
        } catch (Exception $exception) {
            throw new FilesystemException(
                "Unable to publish file '{$destination}'.",
                $failureCode,
                $exception,
            );
        }

        if (!$published) {
            throw new FilesystemException(
                "Unable to publish file '{$destination}'.",
                $failureCode,
            );
        }
    }

    /**
     * @param resource $handle
     */
    private function closeHandle($handle, string $path, int $failureCode): void
    {
        try {
            $closed = @fclose($handle);
        } catch (Exception $exception) {
            throw new FilesystemException(
                "Unable to close file '{$path}'.",
                $failureCode,
                $exception,
            );
        }

        if (!$closed) {
            throw new FilesystemException(
                "Unable to close file '{$path}'.",
                $failureCode,
            );
        }
    }

    /**
     * @param resource|null $handle
     */
    private function closeHandleQuietly($handle): void
    {
        if (!is_resource($handle)) {
            return;
        }

        try {
            @fclose($handle);
        } catch (Throwable) {
        }
    }

    private function removeTemporaryFile(?string $temporary): void
    {
        if ($temporary === null || !$this->pathExists($temporary)) {
            return;
        }

        try {
            @unlink($temporary);
        } catch (Throwable) {
        }
    }

    private function createCopyDestination(string $destination): bool
    {
        if (is_dir($destination)) {
            return false;
        }

        try {
            $created = @mkdir($destination, 0775, true);
        } catch (Exception $exception) {
            if (is_dir($destination)) {
                return false;
            }

            throw new FilesystemException(
                "Unable to create copy destination '{$destination}'.",
                FilesystemException::CODE_COPY_FAILED,
                $exception,
            );
        }

        if ($created) {
            return true;
        }

        if (is_dir($destination)) {
            return false;
        }

        throw new FilesystemException(
            "Unable to create copy destination '{$destination}'.",
            FilesystemException::CODE_COPY_FAILED,
        );
    }

    private function cleanupFailedDirectoryCopy(string $destination, bool $destinationCreated): void
    {
        if (!$destinationCreated || !$this->pathExists($destination)) {
            return;
        }

        try {
            $this->delete($destination);
        } catch (Throwable) {
        }
    }

    private function publicationMode(string $file, ?int $mode): ?int
    {
        if ($mode !== null || !is_file($file)) {
            return $mode;
        }

        try {
            $permissions = @fileperms($file);
        } catch (Exception $exception) {
            throw new FilesystemException(
                "Unable to preserve permissions for '{$file}'.",
                FilesystemException::CODE_FILE_WRITE_FAILED,
                $exception,
            );
        }

        if ($permissions === false) {
            throw new FilesystemException(
                "Unable to preserve permissions for '{$file}'.",
                FilesystemException::CODE_FILE_WRITE_FAILED,
            );
        }

        return $permissions & 0777;
    }

    private function resolveDirectoryPath(string $path): string
    {
        if (!$this->pathExists($path)) {
            throw new FilesystemException(
                "Path '{$path}' not found.",
                FilesystemException::CODE_PATH_NOT_FOUND,
            );
        }

        if (!is_dir($path)) {
            throw new FilesystemException(
                "Path '{$path}' is not a directory.",
                FilesystemException::CODE_PATH_NOT_A_DIRECTORY,
            );
        }

        try {
            $resolved = @realpath($path);
        } catch (Exception $exception) {
            throw new FilesystemException(
                "Unable to resolve directory '{$path}'.",
                FilesystemException::CODE_FILE_READ_FAILED,
                $exception,
            );
        }

        if ($resolved === false) {
            throw new FilesystemException(
                "Unable to resolve directory '{$path}'.",
                FilesystemException::CODE_FILE_READ_FAILED,
            );
        }

        return $resolved;
    }

    private function pathExists(string $path): bool
    {
        return file_exists($path) || is_link($path);
    }
}
