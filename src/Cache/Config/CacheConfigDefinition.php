<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cache\Config;

use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;

final class CacheConfigDefinition extends AbstractConfigDefinition
{
    public static function create(): self
    {
        return new self();
    }

    public static function moduleKey(): string
    {
        return 'cache';
    }

    public function defaultStore(string $store): self
    {
        return $this->set('default', $store);
    }

    public function fileStore(string $path, string $prefix = 'lemonade', int $ttl = 300): self
    {
        return $this
            ->set('stores.file.driver', 'file')
            ->set('stores.file.path', $path)
            ->set('stores.file.prefix', $prefix)
            ->set('stores.file.ttl', $ttl);
    }
}
