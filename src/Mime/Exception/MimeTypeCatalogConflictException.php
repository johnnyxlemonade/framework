<?php

declare(strict_types=1);

namespace Lemonade\Framework\Mime\Exception;

use LogicException;

/**
 * Signals that catalog composition would assign one extension or MIME value to multiple families.
 */
final class MimeTypeCatalogConflictException extends LogicException
{
}
