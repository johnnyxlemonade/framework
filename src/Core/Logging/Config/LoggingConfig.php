<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Logging\Config;

final class LoggingConfig
{
    public function __construct(
        public readonly LoggingChannelConfig $app,
        public readonly LoggingChannelConfig $error,
        public readonly LoggingChannelConfig $request,
        public readonly LoggingChannelConfig $benchmark,
        public readonly int $requestMinStatus,
        public readonly bool $errorLogNotFound,
    ) {}
}
