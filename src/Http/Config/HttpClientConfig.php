<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Config;

final class HttpClientConfig
{
    public function __construct(
        public readonly float $timeout,
        public readonly float $connectTimeout,
        public readonly bool $verifySsl,
    ) {}
}
