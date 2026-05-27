<?php

declare(strict_types=1);

namespace Lemonade\Framework\Filesystem\Manager;

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
use function hash_file;
use function is_file;
use function is_readable;
use function mime_content_type;

use const FILEINFO_MIME_TYPE;

final class FileManager implements FileManagerInterface
{
    /**
     * {@inheritdoc}
     */
    public function read(string $file): string
    {
        if (!is_file($file)) {
            throw new FilesystemException(
                "File '{$file}' not found.",
                FilesystemException::CODE_FILE_NOT_FOUND,
            );
        }

        if (!is_readable($file)) {
            throw new FilesystemException(
                "File '{$file}' is not readable.",
                FilesystemException::CODE_NOT_READABLE,
            );
        }

        $handle = fopen($file, 'rb');
        if ($handle === false) {
            throw new FilesystemException(
                "Failed to open file '{$file}' for reading.",
                FilesystemException::CODE_FILE_READ_FAILED,
            );
        }

        $buffer = '';
        try {
            while (!feof($handle)) {
                $chunk = fread($handle, 8192);
                if ($chunk === false) {
                    throw new FilesystemException(
                        "Failed to read chunk from '{$file}'.",
                        FilesystemException::CODE_FILE_READ_FAILED,
                    );
                }
                $buffer .= $chunk;
            }
        } finally {
            fclose($handle);
        }

        return $buffer;
    }

    /**
     * {@inheritdoc}
     */
    public function size(string $file): int
    {
        if (!is_file($file)) {
            throw new FilesystemException(
                "File '{$file}' not found or is not a file.",
                FilesystemException::CODE_FILE_NOT_FOUND,
            );
        }

        $size = filesize($file);
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
                FilesystemException::CODE_FILE_NOT_FOUND,
            );
        }

        $mtime = filemtime($file);
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
        if (!is_file($file)) {
            throw new FilesystemException(
                "File '{$file}' not found or is not a file.",
                FilesystemException::CODE_FILE_NOT_FOUND,
            );
        }

        $hash = hash_file($algo, $file);
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
        if (!is_file($file)) {
            throw new FilesystemException(
                "File '{$file}' not found or is not a file.",
                FilesystemException::CODE_FILE_NOT_FOUND,
            );
        }

        $mime = false;

        if (extension_loaded('fileinfo')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = finfo_file($finfo, $file);
                finfo_close($finfo);
            }
        }

        if ($mime === false) {
            $mime = mime_content_type($file);
        }

        if ($mime === false) {
            throw new FilesystemException(
                "Failed to determine MIME type for '{$file}'.",
                FilesystemException::CODE_FILE_READ_FAILED,
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
                FilesystemException::CODE_FILE_NOT_FOUND,
            );
        }

        $perms = fileperms($file);
        if ($perms === false) {
            throw new FilesystemException(
                "Failed to get permissions for '{$file}'.",
                FilesystemException::CODE_FILE_READ_FAILED,
            );
        }

        return $perms & 0777;
    }
}
