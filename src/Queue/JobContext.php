<?php

declare(strict_types=1);

namespace Lemonade\Framework\Queue;

/**
 * Immutable snapshot of one queue job execution.
 *
 * It intentionally contains no scope or container reference. A future job
 * scope may bind this value for handlers and job-local services.
 */
final class JobContext
{
    public function __construct(
        public readonly object $message,
        public readonly string $queue,
        public readonly ?int $jobId,
        public readonly int $attempt,
        public readonly string $transport,
    ) {}

    public static function fromQueuedMessage(QueuedMessage $message, string $transport): self
    {
        return new self(
            message: $message->message(),
            queue: $message->queue(),
            jobId: $message->id(),
            attempt: $message->attempts(),
            transport: $transport,
        );
    }
}
