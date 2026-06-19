<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Config;

use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;

final class ProvidersConfigDefinition extends AbstractConfigDefinition
{
    public static function create(): self
    {
        return new self();
    }

    public static function moduleKey(): string
    {
        return 'providers';
    }

    public function provider(string $provider): self
    {
        $this->data[] = $provider;

        return $this;
    }

    /** @param list<string> $providers */
    public function providers(array $providers): self
    {
        $this->data = array_values($providers);

        return $this;
    }
}
