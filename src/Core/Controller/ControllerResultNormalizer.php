<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Controller;

use Lemonade\Framework\Http\HttpStatus;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use RuntimeException;

final class ControllerResultNormalizer
{
    /**
     * @param callable():ResponseFactoryInterface $responseFactoryResolver
     * @param callable():StreamFactoryInterface $streamFactoryResolver
     */
    public function normalize(
        mixed $result,
        callable $responseFactoryResolver,
        callable $streamFactoryResolver,
    ): ResponseInterface {
        if ($result instanceof ResponseInterface) {
            return $result;
        }

        if (is_scalar($result) || $result === null || $result instanceof \Stringable) {
            $responseFactory = $responseFactoryResolver();
            $streamFactory = $streamFactoryResolver();

            return $responseFactory
                ->createResponse(HttpStatus::OK->value)
                ->withHeader('Content-Type', 'text/html; charset=UTF-8')
                ->withBody($streamFactory->createStream((string) $result));
        }

        throw new RuntimeException(sprintf(
            'Controller action result must be scalar|stringable|null or %s, %s given.',
            ResponseInterface::class,
            get_debug_type($result),
        ));
    }
}
