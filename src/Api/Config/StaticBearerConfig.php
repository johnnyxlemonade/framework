<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Config;

/**
 * Holds the configured credential and scopes for the built-in external bearer authenticator.
 */
final readonly class StaticBearerConfig
{
    /**
     * Captures the exact token used for later verification and the permissions assigned on success.
     *
     * @param list<non-empty-string> $scopes
     */
    public function __construct(
        private string $token,
        private array $scopes,
    ) {
    }

    /**
     * Returns the credential that the built-in authenticator verifies in constant time.
     */
    public function token(): string
    {
        return $this->token;
    }

    /**
     * Returns the permissions assigned to an identity authenticated with the configured credential.
     *
     * @return list<non-empty-string>
     */
    public function scopes(): array
    {
        return $this->scopes;
    }
}
