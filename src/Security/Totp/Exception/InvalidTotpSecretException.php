<?php

declare(strict_types=1);

namespace Lemonade\Framework\Security\Totp\Exception;

use InvalidArgumentException;

/**
 * Signals a Base32 credential that is malformed or too short for new TOTP use.
 */
final class InvalidTotpSecretException extends InvalidArgumentException
{
}
