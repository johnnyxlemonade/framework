<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Logging\Config;

final readonly class LoggingChannelConfig
{
    public function __construct(
        public bool $enabled,
        public string $path,
        public string $level,
        public int $days,
    ) {}
}
