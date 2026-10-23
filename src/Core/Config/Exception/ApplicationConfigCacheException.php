<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Config\Exception;

use RuntimeException;

/**
 * Reports an unsafe or unavailable filesystem boundary for compiled application configuration.
 *
 * Corrupted cache data remains a cache miss; this exception is reserved for conditions where the
 * framework cannot safely protect credentials, paths, permissions, keys, or atomic writes.
 */
final class ApplicationConfigCacheException extends RuntimeException
{
}
