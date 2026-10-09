<?php

declare(strict_types=1);

namespace Lemonade\Framework\Security\Totp;

/**
 * Reports whether a code matched and, only on success, which counter step a host may consume for replay protection.
 */
final readonly class TotpVerification
{
    private function __construct(private ?int $matchedTimeStep)
    {
    }

    /**
     * Creates a successful result for framework-internal verification flow only.
     *
     * @internal
     */
    public static function valid(int $matchedTimeStep): self
    {
        if ($matchedTimeStep < 0) {
            throw new \InvalidArgumentException('A matched TOTP time-step must not be negative.');
        }

        return new self($matchedTimeStep);
    }

    /**
     * Creates a failed result for framework-internal verification flow only.
     *
     * @internal
     */
    public static function invalid(): self
    {
        return new self(null);
    }

    /**
     * Indicates whether the submitted code matched one of the permitted counter steps.
     */
    public function isValid(): bool
    {
        return $this->matchedTimeStep !== null;
    }

    /**
     * Returns the matched RFC 6238 counter, or null when verification failed.
     */
    public function matchedTimeStep(): ?int
    {
        return $this->matchedTimeStep;
    }
}
