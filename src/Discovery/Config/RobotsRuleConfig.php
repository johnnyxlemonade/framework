<?php

declare(strict_types=1);

namespace Lemonade\Framework\Discovery\Config;

final class RobotsRuleConfig
{
    /**
     * @param list<non-empty-string> $allow
     * @param list<non-empty-string> $disallow
     */
    public function __construct(
        public string $agent,
        public array $allow,
        public array $disallow,
    ) {}
}
