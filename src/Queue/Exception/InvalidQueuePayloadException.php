<?php

declare(strict_types=1);

namespace Lemonade\Framework\Queue\Exception;

use RuntimeException;
use Throwable;

/**
 * Identifies deterministic queue-envelope failures that must terminate the persisted job.
 *
 * Messages deliberately exclude raw payload data so logs can report the failure without copying
 * attacker-controlled queue contents.
 */
final class InvalidQueuePayloadException extends RuntimeException
{
    /**
     * Reports a malformed, oversized, or non-JSON envelope without exposing its contents.
     */
    public static function malformed(?Throwable $previous = null): self
    {
        return new self('Queue payload envelope is malformed.', 0, $previous);
    }

    /**
     * Reports that the envelope signature does not authenticate its body.
     */
    public static function invalidMac(): self
    {
        return new self('Queue payload integrity verification failed.');
    }

    /**
     * Reports an authenticated type alias absent from the explicit codec registry.
     */
    public static function unknownType(): self
    {
        return new self('Queue payload message type is not registered.');
    }

    /**
     * Reports an authenticated schema version unsupported by the registered codec.
     */
    public static function unsupportedVersion(): self
    {
        return new self('Queue payload message version is not supported.');
    }

    /**
     * Maps a codec's deterministic hydration failure to a terminal payload error.
     */
    public static function codecFailure(Throwable $previous): self
    {
        return new self('Queue payload could not be decoded by its registered codec.', 0, $previous);
    }

    /**
     * Reports a codec that returned an object outside its registered message class.
     */
    public static function codecTypeMismatch(): self
    {
        return new self('Queue message codec returned an unexpected message type.');
    }
}
