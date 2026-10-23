<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Exception;

use RuntimeException;

/**
 * Reports that a session backend cannot preserve its configured persistence or security guarantees.
 */
final class SessionStorageException extends RuntimeException
{
}
