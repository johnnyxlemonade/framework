<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Config;

final readonly class ErrorConfig
{
    public function __construct(
        public string $notFoundView,
        public string $internalServerErrorView,
    ) {}
}
