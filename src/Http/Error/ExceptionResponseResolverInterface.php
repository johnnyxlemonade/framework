<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Error;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Allows an HTTP integration to render selected exceptions before the default HTML fallback is used.
 */
interface ExceptionResponseResolverInterface
{
    public function resolve(
        Throwable $exception,
        ServerRequestInterface $request,
    ): ?ResponseInterface;
}
