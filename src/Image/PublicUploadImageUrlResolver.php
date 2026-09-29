<?php

declare(strict_types=1);

namespace Lemonade\Framework\Image;

use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Image\Contract\ImageUrlResolverInterface;
use Lemonade\Framework\Image\Value\ImageReference;
use Lemonade\Framework\Support\BaseUrlResolver;

/**
 * Resolves public-relative image references under the framework's public uploads path.
 */
final readonly class PublicUploadImageUrlResolver implements ImageUrlResolverInterface
{
    /**
     * Creates a URL resolver constrained to the framework public upload boundary
     */
    public function __construct(
        private readonly BaseUrlResolver $baseUrl,
        private readonly ApplicationContext $context,
    ) {
    }

    /**
     * Resolves an image reference to its absolute public upload URL
     */
    public function url(ImageReference $reference): string
    {
        $relativePath = $this->context->uploadRelativePath(
            $reference->publicPath(),
        );

        return $this->baseUrl->baseUrl($relativePath);
    }
}
