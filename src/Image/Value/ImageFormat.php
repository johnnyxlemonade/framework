<?php

declare(strict_types=1);

namespace Lemonade\Framework\Image\Value;

/** Defines the bitmap formats supported consistently by decoding and encoding infrastructure. */
enum ImageFormat: string
{
    case Jpeg = 'jpeg';
    case Png = 'png';
    case Webp = 'webp';

    public function mimeType(): string
    {
        return match ($this) {
            self::Jpeg => 'image/jpeg',
            self::Png => 'image/png',
            self::Webp => 'image/webp',
        };
    }

    public function extension(): string
    {
        return match ($this) {
            self::Jpeg => 'jpg',
            self::Png => 'png',
            self::Webp => 'webp',
        };
    }

    public static function fromMimeType(string $mimeType): self
    {
        return match (strtolower(trim($mimeType))) {
            'image/jpeg' => self::Jpeg,
            'image/png' => self::Png,
            'image/webp' => self::Webp,
            default => throw new \InvalidArgumentException('Unsupported bitmap image MIME type.'),
        };
    }

    /** @return list<string> */
    public static function supportedMimeTypes(): array
    {
        return array_map(static fn(self $format): string => $format->mimeType(), self::cases());
    }
}
