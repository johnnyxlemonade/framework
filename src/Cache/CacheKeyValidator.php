<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cache;

use Lemonade\Framework\Cache\Exception\InvalidCacheKeyException;

use function preg_match;
use function sprintf;

/**
 * Enforces the PSR-6 key restrictions shared by framework cache implementations.
 */
final class CacheKeyValidator
{
    private const string RESERVED_PATTERN = '~[{}()/\\\\@:]~';

    /**
     * Rejects empty keys and keys containing characters reserved by PSR-6.
     *
     * @throws InvalidCacheKeyException
     */
    public static function assertValid(string $key): void
    {
        if ($key === '' || preg_match(self::RESERVED_PATTERN, $key) === 1) {
            throw new InvalidCacheKeyException(sprintf('Invalid cache key "%s".', $key));
        }
    }
}
