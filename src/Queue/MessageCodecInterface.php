<?php

declare(strict_types=1);

namespace Lemonade\Framework\Queue;

/**
 * Defines an explicit, application-owned mapping between one message object and JSON-compatible data.
 *
 * Implementations must reconstruct only their declared message type and must not perform reflective
 * hydration or resolve a PHP class name from payload data.
 */
interface MessageCodecInterface
{
    /**
     * Converts a registered message into JSON-compatible scalar and array data.
     *
     * @return array<int|string, mixed>
     */
    public function encode(object $message): array;

    /**
     * Reconstructs the codec's declared message type from authenticated payload data.
     *
     * @param array<int|string, mixed> $payload
     */
    public function decode(array $payload): object;
}
