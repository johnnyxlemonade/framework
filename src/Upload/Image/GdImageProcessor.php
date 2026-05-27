<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload\Image;

use GdImage;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Upload\Exception\UploadImageProcessingException;

final class GdImageProcessor
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    public function extensionFromMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new UploadImageProcessingException($this->translator->get(
                'upload.image_mime_not_supported',
                ['mime' => $mime],
            )),
        };
    }

    public function reencode(string $sourcePath, string $targetPath, string $mime): void
    {
        if (!extension_loaded('gd')) {
            throw new UploadImageProcessingException($this->translator->get('upload.gd_not_available'));
        }

        $image = $this->createImageResource($sourcePath, $mime);

        if ($mime === 'image/png') {
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }

        $saved = match ($mime) {
            'image/jpeg' => imagejpeg($image, $targetPath, 90),
            'image/png' => imagepng($image, $targetPath, 6),
            'image/webp' => imagewebp($image, $targetPath, 85),
            default => false,
        };

        imagedestroy($image);

        if ($saved !== true) {
            throw new UploadImageProcessingException($this->translator->get('upload.image_reencode_failed'));
        }
    }

    /**
     * @return array{width:int, height:int}
     */
    public function dimensions(string $path): array
    {
        $imageInfo = getimagesize($path);
        if ($imageInfo === false) {
            throw new UploadImageProcessingException(
                $this->translator->get('upload.stored_image_not_readable'),
            );
        }

        return [
            'width' => $imageInfo[0],
            'height' => $imageInfo[1],
        ];
    }

    private function createImageResource(string $sourcePath, string $mime): GdImage
    {
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($sourcePath),
            'image/png' => @imagecreatefrompng($sourcePath),
            'image/webp' => @imagecreatefromwebp($sourcePath),
            default => false,
        };

        if (!$image instanceof GdImage) {
            throw new UploadImageProcessingException($this->translator->get('upload.image_decode_failed'));
        }

        return $image;
    }

}
