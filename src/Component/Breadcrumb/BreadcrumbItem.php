<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Breadcrumb;

final class BreadcrumbItem
{
    public function __construct(
        private readonly string $label,
        private readonly ?string $url = null,
        private readonly bool $active = false,
    ) {}

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
