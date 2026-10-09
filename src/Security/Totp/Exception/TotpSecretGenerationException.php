<?php

declare(strict_types=1);

namespace Lemonade\Framework\Security\Totp\Exception;

use RuntimeException;

/**
 * Signals that PHP could not obtain cryptographically secure random bytes for a new credential.
 */
final class TotpSecretGenerationException extends RuntimeException
{
}
