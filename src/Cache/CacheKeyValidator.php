<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cache;

use Lemonade\Framework\Cache\Exception\InvalidCacheKeyException;

use function preg_match;
use function sprintf;

final class CacheKeyValidator
{
    private const RESERVED_PATTERN = '~[{}()/\\\\@:]~';

    public static function assertValid(string $key): void
    {
        if ($key === '' || preg_match(self::RESERVED_PATTERN, $key) === 1) {
            throw new InvalidCacheKeyException(sprintf('Invalid cache key "%s".', $key));
        }
    }
}
