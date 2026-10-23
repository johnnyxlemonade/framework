<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Framework\Health;

use JsonException;
use Lemonade\Framework\Api\Http\Response\ApiResponseFactory;
use Lemonade\Framework\Clock\ClockInterface;
use Lemonade\Framework\Core\FrameworkInfo;
use Psr\Http\Message\ResponseInterface;

/**
 * Reports basic framework availability with version identity and a clock-derived timestamp.
 */
final readonly class HealthController
{
    /**
     * Binds health payload creation to API response formatting, time and framework metadata.
     */
    public function __construct(
        private ApiResponseFactory $responses,
        private ClockInterface $clock,
        private FrameworkInfo $frameworkInfo,
    ) {
    }

    /**
     * Returns a successful health payload for the currently running framework instance.
     *
     * @throws JsonException When framework metadata cannot be encoded as JSON.
     */
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
