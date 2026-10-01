<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Breadcrumb;

/**
 * Stores one caller-owned breadcrumb label and its optional navigable URL.
 */
final readonly class BreadcrumbItem
{
    /**
     * Initializes the immutable label and optional URL displayed by a trail.
     */
    public function __construct(
        private string $label,
        private ?string $url = null,
    ) {
    }

    /**
     * Returns the already-localized label supplied by the caller.
     */
    public function label(): string
    {
        return $this->label;
    }

    /**
     * Returns the caller-provided URL or null when the item is not navigable.
     */
    public function url(): ?string
    {
        return $this->url;
    }

}
