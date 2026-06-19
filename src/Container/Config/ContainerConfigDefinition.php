<?php

declare(strict_types=1);

namespace Lemonade\Framework\Container\Config;

use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;

final class ContainerConfigDefinition extends AbstractConfigDefinition
{
    public static function create(): self
    {
        return new self();
    }

    public static function moduleKey(): string
    {
        return 'container';
    }

    public function autowireFallbackWarning(bool $enabled = true): self
    {
        return $this->set('autowire_fallback_warning', $enabled);
    }
}
