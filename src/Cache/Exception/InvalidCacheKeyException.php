<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cache\Exception;

use InvalidArgumentException;
use Psr\Cache\InvalidArgumentException as PsrInvalidArgumentException;

final class InvalidCacheKeyException extends InvalidArgumentException implements PsrInvalidArgumentException {}
