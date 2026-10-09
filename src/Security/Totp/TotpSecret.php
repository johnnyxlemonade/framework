<?php

declare(strict_types=1);

namespace Lemonade\Framework\Security\Totp;

use Lemonade\Framework\Security\Totp\Exception\InvalidTotpSecretException;
use Lemonade\Framework\Security\Totp\Internal\Base32;
use Lemonade\Framework\Security\Totp\Internal\TotpSecretValueStore;
use LogicException;

/**
 * Holds a canonical, minimum-strength Base32 TOTP secret without implicit string conversion.
 */
final readonly class TotpSecret
{
    public const int MINIMUM_BYTES = 20;

    private function __construct()
    {
    }

    /**
     * Imports a formatted Base32 secret and canonicalizes it after enforcing the enrollment minimum.
     *
     * @throws InvalidTotpSecretException
     */
    public static function fromBase32(string $value): self
    {
        $base32 = new Base32();
        $bytes = $base32->decode($value);

        if (strlen($bytes) < self::MINIMUM_BYTES) {
            throw new InvalidTotpSecretException(sprintf(
                'TOTP secret must contain at least %d bytes.',
                self::MINIMUM_BYTES,
            ));
        }

        $secret = new self();
        TotpSecretValueStore::remember($secret, $base32->encode($bytes));

        return $secret;
    }

    /**
     * Exports the secret only through an explicit canonical Base32 operation for trusted consumers.
     */
    public function toBase32(): string
    {
        return TotpSecretValueStore::valueFor($this);
    }

    /**
     * Redacts secret material from PHP debug dumps.
     *
     * @return array{secret:string}
     */
    public function __debugInfo(): array
    {
        return ['secret' => '[redacted]'];
    }

    /**
     * Refuses PHP serialization so secret material cannot be exported through object serialization.
     *
     * @throws LogicException
     */
    public function __serialize(): array
    {
        throw new LogicException('TOTP secrets cannot be serialized.');
    }

    /**
     * Refuses PHP unserialization because serialized TOTP secrets are never accepted by this contract.
     *
     * @param array<array-key, mixed> $data Serialized data rejected by this security boundary.
     *
     * @throws LogicException
     */
    public function __unserialize(array $data): void
    {
        throw new LogicException('TOTP secrets cannot be unserialized.');
    }

    private function __clone()
    {
    }

}
