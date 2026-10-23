<?php

declare(strict_types=1);

namespace Lemonade\Framework\Queue;

/**
 * Carries the opaque ownership fence issued for one persistent delivery attempt.
 *
 * @internal Transport implementations use this value to authorize mutations; applications must not log it.
 */
final readonly class QueueReservationReceipt
{
    /**
     * Captures one cryptographically random database reservation token.
     */
    public function __construct(
        private string $ownerToken,
    ) {
        if (preg_match('/^[a-f0-9]{64}$/D', $ownerToken) !== 1) {
            throw new \InvalidArgumentException('Queue reservation tokens must contain 256 bits encoded as lowercase hexadecimal.');
        }
    }

    /**
     * Returns the ownership token required for fenced transport mutations.
     *
     * @internal The token is transport metadata and must not be logged or persisted in a message payload.
     */
    public function ownerToken(): string
    {
        return $this->ownerToken;
    }
}
