<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule;

use Lemonade\Framework\Validation\Rule\Traits\RegexValidationTrait;

final class ValidYoutubeUrlRule implements ValidationRuleInterface
{
    use RegexValidationTrait;

    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($param, $data);

        return $this->matchesString(
            $value,
            '/^(https?:\/\/)?(www\.)?(youtube\.com\/(watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})$/',
        );
    }
}
