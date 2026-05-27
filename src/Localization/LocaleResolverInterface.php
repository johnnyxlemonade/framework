<?php

declare(strict_types=1);

namespace Lemonade\Framework\Localization;

interface LocaleResolverInterface
{
    public function resolve(): string;
}
