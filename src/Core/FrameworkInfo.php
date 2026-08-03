<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core;

/**
 * Immutable metadata accessor for the framework name, version, and powered-by header values.
 */
final class FrameworkInfo
{
    private const NAME = 'Lemonade Framework';
    private const VERSION = '1.0.0';
    private const POWERED_BY_HEADER = 'X-Powered-Framework';

    /**
     * Returns the stable framework name.
     */
    public function name(): string
    {
        return self::NAME;
    }

    /**
     * Returns the current framework version constant.
     */
    public function version(): string
    {
        return self::VERSION;
    }

    /**
     * Returns the framework name and version in the "NAME / VERSION" format.
     */
    public function fullName(): string
    {
        return self::NAME . ' / ' . self::VERSION;
    }

    /**
     * Returns the HTTP header name used to identify the framework.
     */
    public function poweredByHeader(): string
    {
        return self::POWERED_BY_HEADER;
    }

    /**
     * Returns the value used for the powered-by header.
     *
     * It currently matches the full framework name and version string.
     */
    public function poweredByValue(): string
    {
        return $this->fullName();
    }
}
