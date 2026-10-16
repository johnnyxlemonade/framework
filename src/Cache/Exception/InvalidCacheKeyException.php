<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cache\Exception;

use InvalidArgumentException;
use Psr\Cache\InvalidArgumentException as PsrInvalidArgumentException;

/**
 * Represents rejection of an empty cache key or one containing PSR-6 reserved characters.
 *
 * Consumers may catch this failure through the standard PSR cache exception contract.
 */
final class InvalidCacheKeyException extends InvalidArgumentException implements PsrInvalidArgumentException
{
}
