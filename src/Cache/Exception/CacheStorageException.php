<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cache\Exception;

use Psr\Cache\CacheException;

/**
 * Reports an unsafe or unusable cache persistence boundary that cannot be treated as a cache miss.
 */
final class CacheStorageException extends \RuntimeException implements CacheException
{
}
