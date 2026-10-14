<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Exception;

use InvalidArgumentException;

/**
 * Signals that resolved session configuration selected an unavailable storage driver
 */
final class UnsupportedSessionDriverException extends InvalidArgumentException
{
    /**
     * Creates an exception that lists the recognized storage driver identifiers
     *
     * @param list<string> $supportedDrivers
     */
    public static function forDriver(string $driver, array $supportedDrivers): self
    {
        return new self(sprintf(
            'Unsupported session driver "%s". Supported drivers are: %s.',
            $driver,
            implode(', ', $supportedDrivers),
        ));
    }
}
