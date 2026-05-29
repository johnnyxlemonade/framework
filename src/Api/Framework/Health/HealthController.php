<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Framework\Health;

use Lemonade\Framework\Api\Http\Response\ApiResponseFactory;
use Lemonade\Framework\Clock\ClockInterface;
use Psr\Http\Message\ResponseInterface;

final class HealthController
{
    public function __construct(
        private readonly ApiResponseFactory $responses,
        private readonly ClockInterface $clock,
    ) {}

    public function show(): ResponseInterface
    {
        return $this->responses->ok([
            'status' => 'ok',
            'service' => 'lemonade/framework',
        ], [
            'timestamp' => $this->clock->now()->format(DATE_ATOM),
        ]);
    }
}
