<?php

declare(strict_types=1);

namespace Lemonade\Framework\Image\Value;

use Lemonade\Framework\Image\Exception\ImageValidationException;

/** Identifies a caller-provided local bitmap source without coupling image processing to uploads or requests. */
final readonly class ImageSource
{
    private function __construct(private string $path)
    {
        if (trim($path) === '') {
            throw new ImageValidationException('Image source path must not be empty.');
        }
    }

    public static function fromFile(string $path): self
    {
        return new self($path);
    }

    /** @internal Only image infrastructure may resolve a source path. */
    public function path(): string
    {
        return $this->path;
    }
}
