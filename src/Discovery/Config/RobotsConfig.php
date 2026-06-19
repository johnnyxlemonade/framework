<?php

declare(strict_types=1);

namespace Lemonade\Framework\Discovery\Config;

final class RobotsConfig
{
    /**
     * @param list<RobotsRuleConfig> $rules
     * @param list<non-empty-string> $sitemaps
     */
    public function __construct(
        public bool $enabled,
        public string $route,
        public RobotsHeaderConfig $header,
        public array $rules,
        public array $sitemaps,
    ) {}
}
