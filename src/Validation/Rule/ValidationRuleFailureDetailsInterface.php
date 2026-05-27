<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule;

interface ValidationRuleFailureDetailsInterface
{
    public function pullFailureMessage(): ?string;

    public function pullFailureTranslationKey(): ?string;
}
