<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core;

final class FrameworkInfo
{
    private const NAME = 'Lemonade Framework';
    private const VERSION = '1.0.0';
    private const POWERED_BY_HEADER = 'X-Powered-Framework';

    public function name(): string
    {
        return self::NAME;
    }

    public function version(): string
    {
        return self::VERSION;
    }

    public function fullName(): string
    {
        return self::NAME . ' / ' . self::VERSION;
    }

    public function poweredByHeader(): string
    {
        return self::POWERED_BY_HEADER;
    }

    public function poweredByValue(): string
    {
        return $this->fullName();
    }
}
