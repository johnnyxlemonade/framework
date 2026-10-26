<?php

declare(strict_types=1);

namespace Lemonade\Framework\Image;

use Lemonade\Framework\Filesystem\Filesystem;
use Lemonade\Framework\Image\Contract\ImageFileWriterInterface;
use Lemonade\Framework\Image\Exception\ImageWriteException;
use Lemonade\Framework\Image\Value\EncodedImage;

/**
 * Writes already encoded image bytes to a caller-selected filesystem path.
 * It does not choose storage locations, filenames, public URLs, or retention policy.
 */
final class FilesystemImageFileWriter implements ImageFileWriterInterface
{
    private const int PUBLIC_DIRECTORY_MODE = 0755;
    private const int PUBLIC_FILE_MODE = 0644;

    public function __construct(private readonly Filesystem $filesystem)
    {
    }

    public function write(EncodedImage $image, string $targetPath): void
    {
        if (trim($targetPath) === '') {
            throw new ImageWriteException('Image target path must not be empty.');
        }
        try {
            $this->filesystem->create(dirname($targetPath), self::PUBLIC_DIRECTORY_MODE);
            $this->filesystem->write($targetPath, $image->contents(), self::PUBLIC_FILE_MODE);
        } catch (\Throwable $exception) {
            throw new ImageWriteException('Encoded image cannot be written.', previous: $exception);
        }
    }
}
