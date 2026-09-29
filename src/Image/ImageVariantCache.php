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
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly ImageVariantPathResolver $paths,
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
            return $this->filesystem->lock($target . '.lock', function () use (
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
