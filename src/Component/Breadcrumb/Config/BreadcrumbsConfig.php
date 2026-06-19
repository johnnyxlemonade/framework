<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Breadcrumb\Config;

final class BreadcrumbsConfig
{
    /**
     * @param array<string, string> $classes
     */
    public function __construct(
        public readonly string $frontendRootLabel,
        public readonly string $frontendRootUrl,
        public readonly string $adminRootLabel,
        public readonly string $adminRootUrl,
        public readonly array $classes,
    ) {}
}
