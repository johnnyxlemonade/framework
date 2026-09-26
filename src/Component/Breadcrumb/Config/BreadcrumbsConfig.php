<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Breadcrumb\Config;

final readonly class BreadcrumbsConfig
{
    /**
     * @param array<string, string> $classes
     */
    public function __construct(
        public string $frontendRootLabel,
        public string $frontendRootUrl,
        public string $adminRootLabel,
        public string $adminRootUrl,
        public array $classes,
    ) {}
}
