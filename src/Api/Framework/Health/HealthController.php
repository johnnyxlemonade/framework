<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Framework\Health;

use Lemonade\Framework\Api\Http\Response\ApiResponseFactory;
use Lemonade\Framework\Clock\ClockInterface;
use Lemonade\Framework\Core\FrameworkInfo;
use Psr\Http\Message\ResponseInterface;

final class HealthController
{
    public function __construct(
        private readonly ApiResponseFactory $responses,
        private readonly ClockInterface $clock,
        private readonly FrameworkInfo $frameworkInfo,
    ) {}

    public function show(): ResponseInterface
    {
        return $this->responses->ok([
            'status' => 'ok',
            'service' => $this->frameworkInfo->name(),
            'version' => $this->frameworkInfo->version(),
        ], [
            'timestamp' => $this->clock->now()->format(DATE_ATOM),
        ]);
    }
}
