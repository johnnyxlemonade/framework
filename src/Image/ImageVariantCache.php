<?php

declare(strict_types=1);

namespace Lemonade\Framework\Image;

use Lemonade\Framework\Filesystem\Exception\FilesystemException;
use Lemonade\Framework\Filesystem\Filesystem;
use Lemonade\Framework\Image\Exception\ImageException;
use Lemonade\Framework\Image\Exception\ImageWriteException;
use Lemonade\Framework\Image\Value\EncodedImage;
use Lemonade\Framework\Image\Value\ImageAsset;
use Lemonade\Framework\Image\Value\ImageReference;
use Lemonade\Framework\Image\Value\ImageVariantDefinition;

/**
 * Stores disposable variants atomically and serializes generation for one deterministic target path
 */
final readonly class ImageVariantCache
{
    private const int PUBLIC_DIRECTORY_MODE = 0755;
    private const int PUBLIC_FILE_MODE = 0644;

    public function __construct(
        private Filesystem               $filesystem,
        private ImageVariantPathResolver $paths,
    ) {
    }

    /**
     * Returns an existing public variant reference without starting generation
     */
    public function find(
        ImageAsset $asset,
        ImageVariantDefinition $definition,
    ): ?ImageReference {
        $path = $this->paths->variantPath($asset, $definition);

        return is_file($path) ? $this->paths->reference($asset, $definition) : null;
    }

    /**
     * Generates and atomically publishes a variant unless a matching cached target already exists.
     *
     * @param callable(): EncodedImage $generate
     */
    public function remember(
        ImageAsset $asset,
        ImageVariantDefinition $definition,
        callable $generate,
    ): ImageReference {
        $target = $this->paths->variantPath($asset, $definition);
        $hit = $this->find($asset, $definition);
        if ($hit !== null) {
            return $hit;
        }
        try {
            $this->filesystem->create(dirname($target), self::PUBLIC_DIRECTORY_MODE);

            return $this->filesystem->lock($this->paths->variantLockPath($asset, $definition), function () use (
                $asset,
                $definition,
                $target,
                $generate,
            ): ImageReference {
                $hit = $this->find($asset, $definition);
                if ($hit !== null) {
                    return $hit;
                }
                try {
                    $encoded = $generate();
                    try {
                        $temporary = $target . '.tmp-' . bin2hex(random_bytes(8));
                    } catch (\Throwable $exception) {
                        throw new ImageWriteException('Image variant temporary path cannot be created.', previous: $exception);
                    }
                    $this->filesystem->write(
                        $temporary,
                        $encoded->contents(),
                        self::PUBLIC_FILE_MODE,
                    );
                    $this->filesystem->move($temporary, $target);
                } catch (ImageException $exception) {
                    $this->cleanupTemporary($temporary ?? null);
                    throw $exception;
                } catch (\Throwable $exception) {
                    $this->cleanupTemporary($temporary ?? null);
                    throw new ImageWriteException('Image variant cannot be written.', previous: $exception);
                }
                return $this->paths->reference($asset, $definition);
            });
        } catch (FilesystemException $exception) {
            if ($exception->getPrevious() instanceof ImageException) {
                throw $exception->getPrevious();
            }
            throw new ImageWriteException('Image variant lock cannot be acquired.', previous: $exception);
        }
    }

    /**
     * Removes only variants derived from one exact asset source version.
     */
    public function forget(ImageAsset $asset): void
    {
        $this->filesystem->delete($this->paths->variantDirectory($asset));
    }

    private function cleanupTemporary(?string $temporary): void
    {
        if ($temporary === null) {
            return;
        }

        try {
            $this->filesystem->delete($temporary);
        } catch (\Throwable) {
            // The write failure remains the public contract even when cleanup cannot complete.
        }
    }
}
