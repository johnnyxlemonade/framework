<?php

declare(strict_types=1);

namespace Lemonade\Framework\Queue\Exception;

use RuntimeException;

/**
 * Reports that a database delivery no longer owns a live reservation and therefore cannot mutate its job.
 */
final class QueueLeaseLostException extends RuntimeException
{
    /**
     * Creates the stable failure used when acknowledgement, failure, or renewal loses its ownership fence.
     */
    public static function forDelivery(): self
    {
        return new self('Queue reservation is missing, expired, or owned by another worker.');
    }
}
