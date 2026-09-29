<?php

declare(strict_types=1);

namespace Lemonade\Framework\Image\Value;

/**
 * Is a deterministic SHA-256 identity for a source version and its normalized rendering definition
 */
final readonly class ImageVariantKey
{
    private function __construct(private string $value)
    {
    }

    /**
     * Derives a stable identity from the asset source version and normalized rendering definition
     */
    public static function from(
        ImageAsset $asset,
        ImageVariantDefinition $definition,
    ): self {
        $payload = [
            'asset' => $asset->id(),
            'source_version' => $asset->original()->sourceVersion(),
            'definition' => $definition->canonical(),
        ];

        return new self(
            hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
        );
    }

    /**
     * Returns the SHA-256 value safe for use as a variant filename component
     */
    public function value(): string
    {
        return $this->value;
    }
}
