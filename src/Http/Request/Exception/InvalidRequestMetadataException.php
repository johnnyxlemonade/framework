<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Request\Exception;

use RuntimeException;

/**
 * Represents ambiguous or malformed request metadata that cannot safely cross the proxy trust boundary.
 */
final class InvalidRequestMetadataException extends RuntimeException
{
}
