<?php

declare(strict_types=1);

namespace Lemonade\Framework\Security\Totp;

use Lemonade\Framework\Security\Totp\Exception\InvalidTotpConfigurationException;

/**
 * Holds interoperable TOTP parameters without selecting an issuer, identity, or MFA policy.
 *
 * The period has a contract minimum of one second and deliberately no upper framework limit, because interoperability
 * and acceptable login cadence are host policy rather than a universal TOTP mechanism rule.
 */
final readonly class TotpConfiguration
{
    /**
     * Initializes a configuration with the RFC-compatible SHA-1, 30-second, six-digit default.
     *
     * A period must be positive; its upper bound remains owned by the consuming application.
     *
     * @throws InvalidTotpConfigurationException
     */
    public function __construct(
        public TotpAlgorithm $algorithm = TotpAlgorithm::SHA1,
        public int $periodSeconds = 30,
        public int $digits = 6,
    ) {
        if ($periodSeconds <= 0) {
            throw new InvalidTotpConfigurationException(sprintf(
                'TOTP period must be greater than zero; received %d.',
                $periodSeconds,
            ));
        }

        if (!in_array($digits, [6, 8], true)) {
            throw new InvalidTotpConfigurationException(sprintf(
                'TOTP digits must be 6 or 8; received %d.',
                $digits,
            ));
        }
    }
}
