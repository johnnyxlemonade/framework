<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Config;

final readonly class HttpClientConfig
{
    public function __construct(
        public float $timeout,
        public float $connectTimeout,
        public bool $verifySsl,
    ) {
    }
}
