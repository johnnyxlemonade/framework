<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Config;

use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;

final class SessionConfigDefinition extends AbstractConfigDefinition
{
    public static function create(): self
    {
        return new self();
    }

    public static function moduleKey(): string
    {
        return 'session';
    }

    public function driver(string $driver): self
    {
        return $this->set('driver', $driver);
    }

    public function cookie(string $cookie): self
    {
        return $this->set('cookie', $cookie);
    }

    public function lifetime(int $seconds): self
    {
        return $this->set('lifetime', $seconds);
    }

    public function nativePath(string $path): self
    {
        return $this->set('native.path', $path);
    }

    public function filePath(string $path): self
    {
        return $this->set('file.path', $path);
    }

    public function databaseTable(string $table): self
    {
        return $this->set('database.table', $table);
    }

    public function redisHost(string $host): self
    {
        return $this->set('redis.host', $host);
    }

    public function redisPort(int $port): self
    {
        return $this->set('redis.port', $port);
    }

    public function redisDatabase(int $database): self
    {
        return $this->set('redis.database', $database);
    }

    public function redisPassword(?string $password): self
    {
        return $this->set('redis.password', $password);
    }

    public function redisPrefix(string $prefix): self
    {
        return $this->set('redis.prefix', $prefix);
    }

    public function redisTimeout(float|int $seconds): self
    {
        return $this->set('redis.timeout', $seconds);
    }
}
