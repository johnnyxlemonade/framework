<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Config;

final class ErrorConfig
{
    public function __construct(
        public readonly string $notFoundView,
        public readonly string $internalServerErrorView,
    ) {}
}
