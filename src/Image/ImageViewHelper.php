<?php

declare(strict_types=1);

namespace Lemonade\Framework\Image;

use Lemonade\Framework\Image\Contract\ImageUrlResolverInterface;
use Lemonade\Framework\Image\Value\ImageReference;

/**
 * Renders public image references as escaped HTML image markup.
 * It resolves URLs and presentation attributes only; transformation and persistence happen earlier.
 */
final class ImageViewHelper
{
    public function __construct(private readonly ImageUrlResolverInterface $urls)
    {
    }

    public function image(?ImageReference $reference, ?ImageViewOptions $options = null): string
    {
        if ($reference === null) {
            return '';
        }
        $options ??= new ImageViewOptions();
        $attributes = [
            'src' => $this->urls->url($reference),
            'alt' => $options->alt,
            'loading' => $options->loading,
            'width' => (string) $reference->dimensions()->width,
            'height' => (string) $reference->dimensions()->height,
        ];
        if ($options->class !== null && $options->class !== '') {
            $attributes['class'] = $options->class;
        }
        if ($options->decoding !== null) {
            $attributes['decoding'] = $options->decoding;
        }
        $rendered = [];
        foreach ($attributes as $name => $value) {
            $rendered[] = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }
        return '<img ' . implode(' ', $rendered) . '>';
    }
}
