<?php

declare(strict_types=1);

namespace Lemonade\Framework\Filesystem\Manager;

use Exception;
use Lemonade\Framework\Filesystem\Contract\FileManagerInterface;
use Lemonade\Framework\Filesystem\Exception\FilesystemException;

use function extension_loaded;
use function fclose;
use function feof;
use function file_exists;
use function filemtime;
use function fileperms;
use function filesize;
use function finfo_close;
use function finfo_file;
use function finfo_open;
use function fopen;
use function fread;
use function hash_algos;
use function hash_file;
use function in_array;
use function is_file;
use function is_link;
use function is_readable;
use function mime_content_type;
use function strtolower;

use const FILEINFO_MIME_TYPE;

/**
 * Reads file metadata and contents while normalizing unavailable or unreadable paths to filesystem exceptions.
 */
final class FileManager implements FileManagerInterface
{
    /**
     * {@inheritdoc}
     */
    public function read(string $file): string
    {
        $this->assertRegularFile($file);

        if (!is_readable($file)) {
            throw new FilesystemException(
                "File '{$file}' is not readable.",
                FilesystemException::CODE_NOT_READABLE,
            );
        }

        try {
            $handle = @fopen($file, 'rb');
        } catch (Exception $exception) {
            throw new FilesystemException(
                "Failed to open file '{$file}' for reading.",
                FilesystemException::CODE_FILE_READ_FAILED,
                $exception,
            );
        }

        if ($handle === false) {
            throw new FilesystemException(
                "Failed to open file '{$file}' for reading.",
                FilesystemException::CODE_FILE_READ_FAILED,
            );
        }

        $buffer = '';
        try {
            while (!feof($handle)) {
                try {
                    $chunk = @fread($handle, 8192);
                } catch (Exception $exception) {
                    throw new FilesystemException(
                        "Failed to read chunk from '{$file}'.",
                        FilesystemException::CODE_FILE_READ_FAILED,
                        $exception,
                    );
                }

                if ($chunk === false) {
                    throw new FilesystemException(
                        "Failed to read chunk from '{$file}'.",
                        FilesystemException::CODE_FILE_READ_FAILED,
                    );
                }
                $buffer .= $chunk;
            }
        } finally {
            try {
                @fclose($handle);
            } catch (Exception) {
                // A read failure remains primary; closing the resource is best-effort cleanup.
            }
        }

        return $buffer;
    }

    /**
     * {@inheritdoc}
     */
    public function size(string $file): int
    {
        $this->assertRegularFile($file);

        try {
            $size = @filesize($file);
        } catch (Exception $exception) {
            throw new FilesystemException(
                "Failed to get size of file '{$file}'.",
                FilesystemException::CODE_FILE_READ_FAILED,
                $exception,
            );
        }

        if ($size === false) {
            throw new FilesystemException(
                "Failed to get size of file '{$file}'.",
                FilesystemException::CODE_FILE_READ_FAILED,
            );
        }

        return $size;
    }

    /**
     * {@inheritdoc}
     */
    public function modified(string $file): int
    {
        if (!file_exists($file)) {
            throw new FilesystemException(
                "Path '{$file}' not found.",
                FilesystemException::CODE_PATH_NOT_FOUND,
            );
        }

        try {
            $mtime = @filemtime($file);
        } catch (Exception $exception) {
            throw new FilesystemException(
                "Failed to get modified time of '{$file}'.",
                FilesystemException::CODE_FILE_READ_FAILED,
                $exception,
            );
        }

        if ($mtime === false) {
            throw new FilesystemException(
                "Failed to get modified time of '{$file}'.",
                FilesystemException::CODE_FILE_READ_FAILED,
            );
        }

        return $mtime;
    }

    /**
     * {@inheritdoc}
     */
    public function hash(string $file, string $algo = 'sha256'): string
    {
        $algorithm = strtolower($algo);
        if (!in_array($algorithm, hash_algos(), true)) {
            throw new FilesystemException(
                "Hash algorithm '{$algo}' is not available.",
                FilesystemException::CODE_HASH_ALGORITHM_INVALID,
            );
        }

        $this->assertRegularFile($file);

        try {
            $hash = @hash_file($algorithm, $file);
        } catch (Exception $exception) {
            throw new FilesystemException(
                "Failed to hash file '{$file}' with algorithm '{$algo}'.",
                FilesystemException::CODE_FILE_READ_FAILED,
                $exception,
            );
        }

        if ($hash === false) {
            throw new FilesystemException(
                "Failed to hash file '{$file}' with algorithm '{$algo}'.",
                FilesystemException::CODE_FILE_READ_FAILED,
            );
        }

        return $hash;
    }

    /**
     * {@inheritdoc}
     */
    public function mime(string $file): string
    {
        $this->assertRegularFile($file);

        $mime = false;
        $previous = null;

        if (extension_loaded('fileinfo')) {
            try {
                $finfo = @finfo_open(FILEINFO_MIME_TYPE);
                if ($finfo !== false) {
                    $mime = @finfo_file($finfo, $file);
                    finfo_close($finfo);
                }
            } catch (Exception $exception) {
                $previous = $exception;
            }
        }

        if ($mime === false) {
            try {
                $mime = @mime_content_type($file);
            } catch (Exception $exception) {
                $previous = $exception;
            }
        }

        if ($mime === false) {
            throw new FilesystemException(
                "Failed to determine MIME type for '{$file}'.",
                FilesystemException::CODE_FILE_READ_FAILED,
                $previous,
            );
        }

        return $mime;
    }

    /**
     * {@inheritdoc}
     */
    public function permissions(string $file): int
    {
        if (!file_exists($file)) {
            throw new FilesystemException(
                "Path '{$file}' not found.",
                FilesystemException::CODE_PATH_NOT_FOUND,
            );
        }

        try {
            $perms = @fileperms($file);
        } catch (Exception $exception) {
            throw new FilesystemException(
                "Failed to get permissions for '{$file}'.",
                FilesystemException::CODE_FILE_READ_FAILED,
                $exception,
            );
        }

        if ($perms === false) {
            throw new FilesystemException(
                "Failed to get permissions for '{$file}'.",
                FilesystemException::CODE_FILE_READ_FAILED,
            );
        }

        return $perms & 0777;
    }

    private function assertRegularFile(string $file): void
    {
        if (is_file($file)) {
            return;
        }

        if (file_exists($file) || is_link($file)) {
            throw new FilesystemException(
                "Path '{$file}' is not a regular file.",
                FilesystemException::CODE_FILE_NOT_A_FILE,
            );
        }

        throw new FilesystemException(
            "File '{$file}' not found.",
            FilesystemException::CODE_FILE_NOT_FOUND,
        );
    }
}
