<?php

declare(strict_types=1);

namespace Lemonade\Framework\Queue;

/**
 * Carries a message together with immutable transport metadata for one delivery attempt.
 *
 * Database transports may retain the authenticated raw envelope so failed-job diagnostics preserve
 * exactly what was reserved without serializing the hydrated object again.
 */
final readonly class QueuedMessage
{
    /**
     * Captures a message and its transport-owned delivery metadata.
     */
    public function __construct(
        private object $message,
        private string $queue = 'default',
        private ?int $id = null,
        private int $attempts = 0,
        private ?string $rawPayload = null,
        private ?QueueReservationReceipt $reservationReceipt = null,
    ) {
    }

    /**
     * Returns the hydrated object delivered to its registered handler.
     */
    public function message(): object
    {
        return $this->message;
    }

    /**
     * Returns the logical queue from which this delivery originated.
     */
    public function queue(): string
    {
        return $this->queue;
    }

    /**
     * Returns the persistent transport identifier, or null for an in-memory delivery.
     */
    public function id(): ?int
    {
        return $this->id;
    }

    /**
     * Returns the one-based delivery attempt recorded by the transport.
     */
    public function attempts(): int
    {
        return $this->attempts;
    }

    /**
     * Returns the original authenticated database envelope when the transport retained one.
     */
    public function rawPayload(): ?string
    {
        return $this->rawPayload;
    }

    /**
     * Returns the opaque ownership fence attached by a persistent lease transport.
     *
     * @internal Applications must not log or persist the receipt in a message payload.
     */
    public function reservationReceipt(): ?QueueReservationReceipt
    {
        return $this->reservationReceipt;
    }

    /**
     * Creates a metadata copy for another attempt without changing the message or raw envelope.
     */
    public function withAttempts(int $attempts): self
    {
        return new self(
            message: $this->message,
            queue: $this->queue,
            id: $this->id,
            attempts: $attempts,
            rawPayload: $this->rawPayload,
            reservationReceipt: $this->reservationReceipt,
        );
    }
}
