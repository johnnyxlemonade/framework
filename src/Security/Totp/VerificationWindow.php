<?php

declare(strict_types=1);

namespace Lemonade\Framework\Security\Totp;

use Lemonade\Framework\Security\Totp\Exception\InvalidTotpConfigurationException;

/**
 * Limits clock-skew acceptance around the current time-step to a small fixed maximum.
 */
final readonly class VerificationWindow
{
    public const int MAXIMUM_STEPS_PER_DIRECTION = 2;

    /**
     * Defines the permitted earlier and later steps; each direction is independently bounded at two.
     *
     * The framework bounds verification work and clock skew here, while the host chooses a value within that bound.
     *
     * @throws InvalidTotpConfigurationException
     */
    public function __construct(
        public int $pastSteps = 1,
        public int $futureSteps = 1,
    ) {
        $this->validate('past', $pastSteps);
        $this->validate('future', $futureSteps);
    }

    /**
     * Validates one directional skew allowance against the fixed verification-work bound.
     *
     * @throws InvalidTotpConfigurationException
     */
    private function validate(string $direction, int $steps): void
    {
        if ($steps < 0 || $steps > self::MAXIMUM_STEPS_PER_DIRECTION) {
            throw new InvalidTotpConfigurationException(sprintf(
                'TOTP %s verification steps must be between 0 and %d; received %d.',
                $direction,
                self::MAXIMUM_STEPS_PER_DIRECTION,
                $steps,
            ));
        }
    }
}
