<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Migration\Exception;

use InvalidArgumentException;

/**
 * Signals that a migration class cannot be discovered or mapped to a valid migration identifier.
 */
final class MigrationDiscoveryException extends InvalidArgumentException
{
}
