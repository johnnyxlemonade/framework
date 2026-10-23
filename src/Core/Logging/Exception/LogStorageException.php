<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Logging\Exception;

/**
 * Identifies a log path, permission, identity, or publication state that cannot be made private safely.
 */
final class LogStorageException extends \RuntimeException
{
}
