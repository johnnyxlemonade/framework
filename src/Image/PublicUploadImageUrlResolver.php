<?php

declare(strict_types=1);

namespace Lemonade\Framework\Image;

use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Image\Contract\ImageUrlResolverInterface;
use Lemonade\Framework\Image\Value\ImageReference;

/**
 * Resolves public image references to root-relative upload URLs.
 */
final readonly class PublicUploadImageUrlResolver implements ImageUrlResolverInterface
{
    /**
     * Creates a URL resolver constrained to the framework public upload boundary.
     */
    public function __construct(
        private ApplicationContext $context,
    ) {
    }

    /**
     * Resolves an image reference without requiring a canonical application origin.
     */
    public function url(ImageReference $reference): string
    {
        $relativePath = $this->context->uploadRelativePath(
            $reference->publicPath(),
        );

        return '/' . ltrim($relativePath, '/');
    }
}
