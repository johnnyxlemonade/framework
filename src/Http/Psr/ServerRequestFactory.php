<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Psr;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ServerRequestInterface;

final class ServerRequestFactory
{
    public function __construct(
        private readonly Psr17Factory $psr17Factory,
    ) {}

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
