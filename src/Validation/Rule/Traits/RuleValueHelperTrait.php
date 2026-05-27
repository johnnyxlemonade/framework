<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule\Traits;

trait RuleValueHelperTrait
{
    protected function isEmptyString(?string $value): bool
    {
        return $value === null || $value === '';
    }

    /**
     * @return list<int>
     */
    protected function getPhoneDialingCode(): array
    {
        return [
            421, 420, 423, 299, 351, 352, 353, 354, 356, 358, 359, 365, 370, 371,
            372, 373, 375, 376, 377, 378, 379, 380, 381, 382, 385, 386, 387, 389,
            30, 31, 32, 33, 34, 36, 39, 43, 45, 40, 41, 44, 46, 47, 48, 49,
        ];
    }

    /**
     * @return list<string>
     */
    protected function formatDialingCode(string $number): array
    {
        $length = strlen($number);

        return array_map(
            static fn(int $i): string => str_pad($number, $i, '0', STR_PAD_LEFT),
            range($length, 10),
        );
    }
}
