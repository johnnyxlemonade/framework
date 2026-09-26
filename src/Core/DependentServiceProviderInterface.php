<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core;

interface DependentServiceProviderInterface
{
    /** @return list<class-string> */
    public static function requires(): array;
}
