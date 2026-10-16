<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session;

/**
 * Generates and validates canonical identifiers for non-native session storage.
 *
 * Identifiers contain 32 cryptographically secure random bytes encoded as 64 lowercase
 * hexadecimal characters.
 */
final readonly class SessionIdGenerator
{
    private const int RANDOM_BYTES = 32;
    private const string PATTERN = '/^[a-f0-9]{64}$/D';

    /**
     * Generates a cryptographically secure canonical session identifier.
     */
    public function generate(): string
    {
        return bin2hex(random_bytes(self::RANDOM_BYTES));
    }

    /**
     * Reports whether a value has the canonical non-native session identifier format.
     */
    public function isValid(string $sessionId): bool
    {
        return preg_match(self::PATTERN, $sessionId) === 1;
    }
}
