<?php

declare(strict_types=1);

namespace Lemonade\Framework\Security\Totp\Exception;

use InvalidArgumentException;

/**
 * Signals a TOTP setting which cannot safely produce or verify interoperable codes.
 */
final class InvalidTotpConfigurationException extends InvalidArgumentException
{
}
