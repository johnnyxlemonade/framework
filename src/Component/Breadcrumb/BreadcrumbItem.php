<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Breadcrumb;

final readonly class BreadcrumbItem
{
    public function __construct(
        private string $label,
        private ?string $url = null,
        private bool $active = false,
    ) {
    }

    public function label(): string
    {
        return $this->label;
    }

    public function url(): ?string
    {
        return $this->url;
    }

    public function active(): bool
    {
        return $this->active;
    }
}
