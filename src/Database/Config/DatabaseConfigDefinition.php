<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Config;

use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;
use Lemonade\Framework\Core\Config\Internal\ConfigValueNormalizer;

/**
 * Collects named database connection contributions for layered framework configuration.
 */
final class DatabaseConfigDefinition extends AbstractConfigDefinition
{
    /**
     * Starts an empty database configuration contribution for one configuration source.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Returns the stable configuration module key consumed by the database service provider.
     */
    public static function moduleKey(): string
    {
        return 'database';
    }

    /**
     * Selects the named connection used when consumers request the unqualified database service.
     *
     * A null name deliberately clears an earlier default contribution.
     */
    public function defaultConnection(?string $name): self
    {
        return $this->set('default', $name);
    }

    /**
     * Adds or replaces one named connection contribution without changing other connection entries.
     *
     * @param array<string, mixed> $config
     */
    public function connection(string $name, array $config): self
    {
        $name = trim($name);

        if ($name === '') {
            return $this;
        }

        $normalized = ConfigValueNormalizer::assoc($config);

        if (!isset($this->data['connections']) || !is_array($this->data['connections'])) {
            $this->data['connections'] = [];
        }

        $connections = $this->data['connections'];
        $connections[$name] = $normalized;
        $this->data['connections'] = $connections;

        return $this;
    }
}
