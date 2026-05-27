<?php

declare(strict_types=1);

namespace Lemonade\Framework\Filesystem\Manager;

use FilesystemIterator;
use Generator;
use GlobIterator;
use Lemonade\Framework\Filesystem\Contract\DirectoryManagerInterface;
use Lemonade\Framework\Filesystem\Exception\FilesystemException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

use SplFileInfo;
use Throwable;

use function decoct;
use function dirname;
use function fclose;
use function file_exists;
use function fopen;
use function is_dir;
use function is_file;
use function is_link;
use function mkdir;
use function rmdir;
use function stream_copy_to_stream;

final class DirectoryManager implements DirectoryManagerInterface
{
    /**
     * {@inheritdoc}
     */
    public function create(string $pathIterator, int $mode = 0775): void
    {
        if (!is_dir($pathIterator) && !@mkdir($pathIterator, $mode, true) && !is_dir($pathIterator)) {
            throw new FilesystemException(
                sprintf("Unable to create directory '%s' with mode %s.", $pathIterator, decoct($mode)),
                FilesystemException::CODE_DIR_CREATE_FAILED,
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function copy(string $src, string $dst, bool $overwrite = false): void
    {
        if (stream_is_local($src) && !file_exists($src)) {
            throw new FilesystemException(
                "File or directory '{$src}' not found.",
                FilesystemException::CODE_FILE_NOT_FOUND,
            );
        }

        if (!$overwrite && file_exists($dst)) {
            throw new FilesystemException(
                "File or directory '{$dst}' already exists.",
                FilesystemException::CODE_COPY_FAILED,
            );
        }

        if (is_dir($src)) {
            $this->copyDirectory($src, $dst, $overwrite);

            return;
        }

        $this->copyFile($src, $dst, $overwrite);
    }

    private function copyDirectory(string $src, string $dst, bool $overwrite): void
    {
        $this->create($dst);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            if (!$item instanceof SplFileInfo) {
                continue;
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
    }

    private function copyFile(string $src, string $dst, bool $overwrite): void
    {
        if (!$overwrite && file_exists($dst)) {
            throw new FilesystemException(
                "File '{$dst}' already exists.",
                FilesystemException::CODE_FILE_CONFLICT,
            );
        }

        $this->create(dirname($dst));

        $srcHandle = fopen($src, 'rb');
        if ($srcHandle === false) {
            throw new FilesystemException(
                "Unable to open source file '{$src}' for reading.",
                FilesystemException::CODE_COPY_FAILED,
            );
        }

        $dstHandle = fopen($dst, 'wb');
        if ($dstHandle === false) {
            fclose($srcHandle);

            throw new FilesystemException(
                "Unable to open destination file '{$dst}' for writing.",
                FilesystemException::CODE_COPY_FAILED,
            );
        }

        try {
            if (stream_copy_to_stream($srcHandle, $dstHandle) === false) {
                throw new FilesystemException(
                    "Unable to copy file '{$src}' to '{$dst}'.",
                    FilesystemException::CODE_COPY_FAILED,
                );
            }
        } finally {
            fclose($srcHandle);
            fclose($dstHandle);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $file, string $data, ?int $mode = 0666): void
    {
        $this->create(dirname($file));

        if (@file_put_contents($file, $data) === false) {
            throw new FilesystemException(
                "Unable to write file '{$file}'.",
                FilesystemException::CODE_FILE_WRITE_FAILED,
            );
        }

        if ($mode !== null && !@chmod($file, $mode)) {
            throw new FilesystemException(
                "Unable to chmod file '{$file}' to mode " . decoct($mode) . '.',
                FilesystemException::CODE_FILE_WRITE_FAILED,
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $path): void
    {
        if (is_file($path) || is_link($path)) {
            $func = DIRECTORY_SEPARATOR === '\\' && is_dir($path) ? 'rmdir' : 'unlink';
            if (!@$func($path)) {
                throw new FilesystemException("Unable to delete '{$path}'.", FilesystemException::CODE_DIR_DELETE_FAILED);
            }
            return;
        }

        if (is_dir($path)) {
            /** @var SplFileInfo $item */
            foreach (new FilesystemIterator($path) as $item) {
                $this->delete($item->getPathname());
            }

            if (!@rmdir($path)) {
                throw new FilesystemException(
                    "Unable to delete directory '{$path}'.",
                    FilesystemException::CODE_DIR_DELETE_FAILED,
                );
            }
        }
    }

    /**
     * Vrací lazy seznam položek adresáře (název => absolutní cesta).
     *
     * @return Generator<string, string>
     * @throws FilesystemException
     */
    public function stream(string $path, bool $recursive = false): Generator
    {
        if (!is_dir($path)) {
            throw new FilesystemException("Path '{$path}' is not a directory.", FilesystemException::CODE_FILE_NOT_FOUND);
        }

        try {
            $iterator = $recursive
                ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS))
                : new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS);

            foreach ($iterator as $item) {
                if ($item instanceof SplFileInfo) {
                    yield $item->getFilename() => $item->getRealPath();
                }
            }
        } catch (Throwable $e) {
            throw new FilesystemException(
                "Failed to stream directory '{$path}': {$e->getMessage()}",
                FilesystemException::CODE_FILE_READ_FAILED,
                $e,
            );
        }
    }

    /**
     * Prochází adresář (rekurzivně nebo jen první úroveň) a vrací SplFileInfo objekty.
     *
     * @return Generator<int, SplFileInfo>
     * @throws FilesystemException
     */
    public function tree(string $path, bool $recursive = true): Generator
    {
        if (!is_dir($path)) {
            throw new FilesystemException("Path '{$path}' is not a directory.", FilesystemException::CODE_FILE_NOT_FOUND);
        }

        try {
            $base = new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO);

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
        } catch (Throwable $e) {
            throw new FilesystemException(
                "Failed to walk directory '{$path}': {$e->getMessage()}",
                FilesystemException::CODE_FILE_READ_FAILED,
                $e,
            );
        }
    }

    /**
     * Vyhledá soubory podle vzoru v daném adresáři (neprobíhá rekurze).
     *
     * @return Generator<int, string>
     * @throws FilesystemException
     */

    public function find(string $pattern, string $path): Generator
    {
        if (!is_dir($path)) {
            throw new FilesystemException(
                "Path '{$path}' is not a directory.",
                FilesystemException::CODE_FILE_NOT_FOUND,
            );
        }

        $fullPattern = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $pattern;

        try {
            $iterator = new GlobIterator($fullPattern, FilesystemIterator::SKIP_DOTS);
        } catch (Throwable $e) {
            throw new FilesystemException(
                "Failed to initialize GlobIterator for pattern '{$fullPattern}': {$e->getMessage()}",
                FilesystemException::CODE_FILE_READ_FAILED,
                $e,
            );
        }

        foreach ($iterator as $fileInfo) {
            if ($fileInfo instanceof SplFileInfo) {
                yield $fileInfo->getPathname();
            }
        }
    }

}
