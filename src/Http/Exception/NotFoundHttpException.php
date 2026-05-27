<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Exception;

use RuntimeException;

final class NotFoundHttpException extends RuntimeException
{
    public static function create(string $message = 'Resource not found.'): self
    {
        return new self($message);
    }
}
