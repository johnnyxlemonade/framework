<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Logging\Config;

final class LoggingChannelConfig
{
    public function __construct(
        public readonly bool $enabled,
        public readonly string $path,
        public readonly string $level,
        public readonly int $days,
    ) {}
}
