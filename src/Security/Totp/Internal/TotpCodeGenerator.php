<?php

declare(strict_types=1);

namespace Lemonade\Framework\Security\Totp\Internal;

use Lemonade\Framework\Security\Totp\TotpConfiguration;
use Lemonade\Framework\Security\Totp\TotpSecret;

/**
 * Computes RFC 6238 codes from already validated secret bytes and a counter time-step.
 *
 * @internal
 */
final class TotpCodeGenerator
{
    /**
     * Produces one fixed-width OTP for a non-negative Unix timestamp.
     */
    public function generate(TotpSecret $secret, int $timestamp, TotpConfiguration $configuration): string
    {
        $timeStep = intdiv($timestamp, $configuration->periodSeconds);
        $counter = pack('N2', intdiv($timeStep, 4294967296), $timeStep % 4294967296);
        $secretBytes = (new Base32())->decode($secret->toBase32());
        $hash = hash_hmac($configuration->algorithm->value, $counter, $secretBytes, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $value = unpack('Nvalue', substr($hash, $offset, 4));

        if ($value === false || !is_int($value['value'])) {
            throw new \LogicException('TOTP HMAC output could not be decoded.');
        }

        $code = ($value['value'] & 0x7FFFFFFF) % (10 ** $configuration->digits);

        return str_pad((string) $code, $configuration->digits, '0', STR_PAD_LEFT);
    }
}
