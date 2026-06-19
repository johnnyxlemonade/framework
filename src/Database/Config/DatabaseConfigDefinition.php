<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Config;

use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;

final class DatabaseConfigDefinition extends AbstractConfigDefinition
{
    public static function create(): self
    {
        return new self();
    }

    public static function moduleKey(): string
    {
        return 'database';
    }

    public function defaultConnection(?string $name): self
    {
        return $this->set('default', $name);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function connection(string $name, array $config): self
    {
        $name = trim($name);

        if ($name === '') {
            return $this;
        }

        $normalized = [];

        foreach ($config as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        if (!isset($this->data['connections']) || !is_array($this->data['connections'])) {
            $this->data['connections'] = [];
        }

        $connections = $this->data['connections'];
        $connections[$name] = $normalized;
        $this->data['connections'] = $connections;

        return $this;
    }
}
