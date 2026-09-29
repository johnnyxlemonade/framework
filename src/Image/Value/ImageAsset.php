<?php

declare(strict_types=1);

namespace Lemonade\Framework\Image\Value;

use Lemonade\Framework\Image\Exception\ImageValidationException;

/**
 * Identifies one persistent image asset without assigning it an application-specific owner
 */
final readonly class ImageAsset
{
    /**
     * Creates a path-safe asset identity bound to its immutable persistent original
     */
    public function __construct(
        private string $id,
        private ImageOriginalReference $original,
    ) {
        if (preg_match('/^[A-Za-z0-9_-]+$/', $id) !== 1) {
            throw new ImageValidationException('Image asset ID must be path-safe.');
        }
    }

    /**
     * Returns the path-safe identity shared by the original and all derived variants
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * Returns the immutable reference that locates the persistent source
     */
    public function original(): ImageOriginalReference
    {
        return $this->original;
    }
}
