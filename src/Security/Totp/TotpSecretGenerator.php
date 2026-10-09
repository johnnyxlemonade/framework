<?php

declare(strict_types=1);

namespace Lemonade\Framework\Security\Totp;

use Lemonade\Framework\Security\Totp\Exception\TotpSecretGenerationException;
use Throwable;

/**
 * Creates new 160-bit TOTP credentials using PHP's operating-system CSPRNG only.
 */
final class TotpSecretGenerator
{
    /**
     * Generates the framework minimum-strength secret and never falls back to a weaker random source.
     *
     * @throws TotpSecretGenerationException
     */
    public function generate(): TotpSecret
    {
        try {
            $bytes = random_bytes(TotpSecret::MINIMUM_BYTES);
        } catch (Throwable $exception) {
            throw new TotpSecretGenerationException(
                'Unable to generate a cryptographically secure TOTP secret.',
                0,
                $exception,
            );
        }

        return TotpSecret::fromBase32((new Internal\Base32())->encode($bytes));
    }
}
