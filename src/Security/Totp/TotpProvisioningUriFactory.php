<?php

declare(strict_types=1);

namespace Lemonade\Framework\Security\Totp;

use InvalidArgumentException;

/**
 * Builds a standard otpauth URI and leaves QR rendering and delivery entirely to the consumer.
 */
final class TotpProvisioningUriFactory
{
    /**
     * Encodes a host-owned issuer and account label together with the selected credential settings.
     *
     * @throws InvalidArgumentException
     */
    public function create(
        string $issuer,
        string $accountLabel,
        TotpSecret $secret,
        TotpConfiguration $configuration,
    ): string {
        $issuer = trim($issuer);
        $accountLabel = trim($accountLabel);

        if ($issuer === '') {
            throw new InvalidArgumentException('TOTP provisioning issuer must not be empty.');
        }

        if ($accountLabel === '') {
            throw new InvalidArgumentException('TOTP provisioning account label must not be empty.');
        }

        return sprintf(
            'otpauth://totp/%s?%s',
            rawurlencode($issuer . ':' . $accountLabel),
            http_build_query([
                'secret' => $secret->toBase32(),
                'issuer' => $issuer,
                'algorithm' => match ($configuration->algorithm) {
                    TotpAlgorithm::SHA1 => 'SHA1',
                    TotpAlgorithm::SHA256 => 'SHA256',
                    TotpAlgorithm::SHA512 => 'SHA512',
                },
                'digits' => $configuration->digits,
                'period' => $configuration->periodSeconds,
            ], '', '&', PHP_QUERY_RFC3986),
        );
    }
}
