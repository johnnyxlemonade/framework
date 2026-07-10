<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Config;

use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;

final class IntegrationsConfigDefinition extends AbstractConfigDefinition
{
    public static function create(): self
    {
        return new self();
    }

    public static function moduleKey(): string
    {
        return 'integrations';
    }
}
