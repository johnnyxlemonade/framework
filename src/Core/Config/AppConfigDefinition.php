<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Config;

use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;

final class AppConfigDefinition extends AbstractConfigDefinition
{
    public static function create(): self
    {
        return new self();
    }

    public static function moduleKey(): string
    {
        return 'app';
    }

    public function timezone(?string $timezone): self
    {
        return $this->set('timezone', $timezone);
    }

    public function baseUrl(?string $baseUrl): self
    {
        return $this->set('base_url', $baseUrl);
    }

    public function basePath(string $basePath): self
    {
        return $this->set('base_path', $basePath);
    }

    public function env(string $environment): self
    {
        return $this->set('env', $environment);
    }

    public function debug(bool $debug = true): self
    {
        return $this->set('debug', $debug);
    }

    public function appPath(string $appPath): self
    {
        return $this->set('app_path', $appPath);
    }

    public function configPath(string $configPath): self
    {
        return $this->set('config_path', $configPath);
    }

    public function publicPath(string $publicPath): self
    {
        return $this->set('public_path', $publicPath);
    }

    public function storagePath(string $storagePath): self
    {
        return $this->set('storage_path', $storagePath);
    }
}
