<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Config;

use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;

final class FrameworkConfigDefinition extends AbstractConfigDefinition
{
    public static function create(): self
    {
        return new self();
    }

    public static function moduleKey(): string
    {
        return 'framework';
    }

    public function provider(string $provider): self
    {
        return $this->append('providers', $provider);
    }

    /**
     * @param list<string> $providers
     */
    public function providers(array $providers): self
    {
        return $this->set('providers', array_values($providers));
    }
}
