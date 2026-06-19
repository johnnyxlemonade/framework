<?php

declare(strict_types=1);

namespace Lemonade\Framework\View\Config;

final class ViewConfig
{
    public function __construct(
        public readonly string $basePath,
    ) {}
}
