<?php

declare(strict_types=1);

namespace Lemonade\Framework\Discovery\Config;

final class RobotsHeaderConfig
{
    public function __construct(
        public bool $enabled,
        public string $generator,
        public string $dateFormat,
    ) {}
}
