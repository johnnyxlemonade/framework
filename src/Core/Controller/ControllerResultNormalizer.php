<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Controller;

use Lemonade\Framework\Http\Response\Responses;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final class ControllerResultNormalizer
{
    public function __construct(
        private readonly Responses $responses,
    ) {}

    public function normalize(
        mixed $result,
    ): ResponseInterface {
        if ($result instanceof ResponseInterface) {
            return $result;
        }

        if (is_scalar($result) || $result === null || $result instanceof \Stringable) {
            return $this->responses->html((string) $result);
        }

        throw new RuntimeException(sprintf(
            'Controller action result must be scalar|stringable|null or %s, %s given.',
            ResponseInterface::class,
            get_debug_type($result),
        ));
    }
}
