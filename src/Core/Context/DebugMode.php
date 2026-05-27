<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Context;

final class DebugMode
{
    public function __construct(
        private readonly bool $enabled,
    ) {}

    public static function enabled(): self
    {
        return new self(true);
    }

    public static function disabled(): self
    {
        return new self(false);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function isDisabled(): bool
    {
        return !$this->enabled;
    }

    public function value(): bool
    {
        return $this->enabled;
    }
}
