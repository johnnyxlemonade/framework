<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Logging\Config;

final readonly class LoggingConfig
{
    public function __construct(
        public LoggingChannelConfig $app,
        public LoggingChannelConfig $error,
        public LoggingChannelConfig $request,
        public LoggingChannelConfig $benchmark,
        public int $requestMinStatus,
        public bool $errorLogNotFound,
    ) {
    }
}
