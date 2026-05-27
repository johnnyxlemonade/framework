<?php

declare(strict_types=1);

namespace Lemonade\Framework\Queue;

final class MessageSerializer
{
    public function encode(object $message): string
    {
        return base64_encode(serialize($message));
    }

    public function decode(string $payload): object
    {
        $decoded = base64_decode($payload, true);
        if ($decoded === false) {
            throw new \RuntimeException('Invalid queue payload encoding.');
        }

        $message = unserialize($decoded, ['allowed_classes' => true]);
        if (!is_object($message)) {
            throw new \RuntimeException('Queue payload does not contain an object message.');
        }

        return $message;
    }
}
