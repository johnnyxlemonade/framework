<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Psr;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Creates PSR-7 server requests from the active PHP request globals.
 */
final readonly class ServerRequestFactory
{
    /**
     * Reuses one PSR-17 factory for request, URI, upload, and stream creation.
     */
    public function __construct(
        private Psr17Factory $psr17Factory,
    ) {
    }

    /**
     * Captures the current global request state in a new server request.
     */
    public function fromGlobals(): ServerRequestInterface
    {
        $creator = new ServerRequestCreator(
            $this->psr17Factory,
            $this->psr17Factory,
            $this->psr17Factory,
            $this->psr17Factory,
        );

        return $creator->fromGlobals();
    }
}
