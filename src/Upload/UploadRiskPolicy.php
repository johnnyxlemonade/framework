<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload;

use InvalidArgumentException;
use Lemonade\Framework\Mime\MimeRisk;

/**
 * Applies the existing classified-format policy to private uploads.
 *
 * Private storage accepts every
 * non-passive category only when the profile explicitly lists that category.
 */
final readonly class UploadRiskPolicy
{
    /**
     * Reports whether the risk category is authorized by the explicit private-storage policy.
     *
     * @param list<MimeRisk> $allowedRisks Explicit non-passive categories authorized for private storage
     */
    public function allows(
        MimeRisk $risk,
        array $allowedRisks = [],
    ): bool {
        $this->assertRiskList($allowedRisks);

        if ($risk === MimeRisk::Passive) {
            return true;
        }

        return in_array($risk, $allowedRisks, true);
    }

    /**
     * Rejects malformed policy lists before they can silently weaken an upload decision.
     *
     * @param list<MimeRisk> $allowedRisks
     */
    private function assertRiskList(array $allowedRisks): void
    {
        foreach ($allowedRisks as $risk) {
            if (!$risk instanceof MimeRisk) {
                throw new InvalidArgumentException('Upload allowed_risks must contain only MimeRisk values.');
            }
        }
    }
}
