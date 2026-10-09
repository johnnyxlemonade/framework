<?php

declare(strict_types=1);

namespace Lemonade\Framework\Security\Totp;

use Lemonade\Framework\Clock\ClockInterface;
use Lemonade\Framework\Security\Totp\Internal\TotpCodeGenerator;

/**
 * Verifies a fixed-width TOTP code at the framework clock without retaining replay or identity state.
 */
final readonly class TotpVerifier
{
    private TotpCodeGenerator $generator;

    /**
     * Uses the framework clock so callers and tests share one explicit time boundary.
     */
    public function __construct(private ClockInterface $clock)
    {
        $this->generator = new TotpCodeGenerator();
    }

    /**
     * Verifies the current time-step before alternating nearest past and future steps deterministically.
     */
    public function verify(
        TotpSecret $secret,
        string $code,
        TotpConfiguration $configuration,
        VerificationWindow $window = new VerificationWindow(),
    ): TotpVerification {
        $code = trim($code);

        if (!ctype_digit($code) || strlen($code) !== $configuration->digits) {
            return TotpVerification::invalid();
        }

        $currentTimeStep = intdiv($this->clock->now()->getTimestamp(), $configuration->periodSeconds);

        foreach ($this->candidateTimeSteps($currentTimeStep, $window) as $timeStep) {
            $candidate = $this->generator->generate(
                $secret,
                $timeStep * $configuration->periodSeconds,
                $configuration,
            );

            if (hash_equals($candidate, $code)) {
                return TotpVerification::valid($timeStep);
            }
        }

        return TotpVerification::invalid();
    }

    /**
     * Returns current, then each nearest past/future pair without producing negative counters.
     *
     * @return list<int>
     */
    private function candidateTimeSteps(int $currentTimeStep, VerificationWindow $window): array
    {
        $timeSteps = [$currentTimeStep];
        $maximumDistance = max($window->pastSteps, $window->futureSteps);

        for ($distance = 1; $distance <= $maximumDistance; $distance++) {
            $pastTimeStep = $currentTimeStep - $distance;
            if ($distance <= $window->pastSteps && $pastTimeStep >= 0) {
                $timeSteps[] = $pastTimeStep;
            }

            if ($distance <= $window->futureSteps) {
                $timeSteps[] = $currentTimeStep + $distance;
            }
        }

        return $timeSteps;
    }
}
