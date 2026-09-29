<?php

declare(strict_types=1);

namespace Lemonade\Framework\View\Config;

final readonly class ViewConfig
{
    public function __construct(
        public string $basePath,
    ) {
    }
}
