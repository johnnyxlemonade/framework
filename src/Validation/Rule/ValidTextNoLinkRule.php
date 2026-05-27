<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule;

use Lemonade\Framework\Validation\Rule\Traits\RegexValidationTrait;

final class ValidTextNoLinkRule implements ValidationRuleInterface
{
    use RegexValidationTrait;

    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($param, $data);

        if (!is_string($value)) {
            return false;
        }

        $text = html_entity_decode(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return !$this->matchesString(
            $text,
            '~(?:https?://|www\.|href\s*=|mailto:|ftp://|[a-z0-9.-]+\.[a-z]{2,})\S*~iu',
        );
    }
}
