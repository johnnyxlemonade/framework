<?php

declare(strict_types=1);

namespace Lemonade\Framework\Discovery\Robots;

final class RobotsRule
{
    /**
     * @param list<string> $allow
     * @param list<string> $disallow
     */
    public function __construct(
        private readonly string $userAgent,
        private readonly array $allow = [],
        private readonly array $disallow = [],
    ) {}

    public function userAgent(): string
    {
        return $this->userAgent;
    }

    /**
     * @return list<string>
     */
    public function allow(): array
    {
        return $this->allow;
    }

    /**
     * @return list<string>
     */
    public function disallow(): array
    {
        return $this->disallow;
    }
}
