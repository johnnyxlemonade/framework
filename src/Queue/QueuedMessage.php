<?php

declare(strict_types=1);

namespace Lemonade\Framework\Queue;

final class QueuedMessage
{
    public function __construct(
        private readonly object $message,
        private readonly string $queue = 'default',
        private readonly ?int $id = null,
        private readonly int $attempts = 0,
    ) {}

    public function message(): object
    {
        return $this->message;
    }

    public function queue(): string
    {
        return $this->queue;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function attempts(): int
    {
        return $this->attempts;
    }

    public function withAttempts(int $attempts): self
    {
        return new self(
            message: $this->message,
            queue: $this->queue,
            id: $this->id,
            attempts: $attempts,
        );
    }
}
