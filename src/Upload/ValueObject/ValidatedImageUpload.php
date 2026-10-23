<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload\ValueObject;

use Lemonade\Framework\Image\Value\DecodedImage;
use Lemonade\Framework\Mime\MimeType;

/**
 * Couples the detected MIME family with the fully decoded pixels verified from the same staged snapshot.
 *
 * @internal UploadService consumes this value before choosing copy or re-encode publication.
 */
final readonly class ValidatedImageUpload
{
    /**
     * Retains the results of MIME policy and complete image decoding for one immutable snapshot.
     */
    public function __construct(
        private MimeType $mimeType,
        private DecodedImage $decodedImage,
    ) {
    }

    /**
     * Returns the server-detected MIME value authorized by the upload policy.
     */
    public function mimeType(): MimeType
    {
        return $this->mimeType;
    }

    /**
     * Returns normalized pixels produced by a complete supported-format decode.
     */
    public function decodedImage(): DecodedImage
    {
        return $this->decodedImage;
    }
}
