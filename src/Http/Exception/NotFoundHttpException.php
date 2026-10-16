<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Exception;

use RuntimeException;

/**
 * Marks an application-level resource miss that HTTP error handling maps to a 404 response.
 */
final class NotFoundHttpException extends RuntimeException
{
    /**
     * Creates the failure with a caller-facing diagnostic message.
     */
    public static function create(string $message = 'Resource not found.'): self
    {
        return new self($message);
    }
}
