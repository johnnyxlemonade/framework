<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Exception;

use InvalidArgumentException;

final class UnsupportedSessionDriverException extends InvalidArgumentException
{
    /**
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
