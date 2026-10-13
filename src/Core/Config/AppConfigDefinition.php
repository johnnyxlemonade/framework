<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Config;

use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;

/**
 * Defines application-wide runtime values supplied by the host application.
 */
final class AppConfigDefinition extends AbstractConfigDefinition
{
    /**
     * Creates an empty application configuration definition.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Returns the configuration module key used by the application configuration resolver.
     */
    public static function moduleKey(): string
    {
        return 'app';
    }

    /**
     * Sets the default timezone for the application.
     */
    public function timezone(?string $timezone): self
    {
        return $this->set('timezone', $timezone);
    }

    /**
     * Sets the public base URL of the application.
     */
    public function baseUrl(?string $baseUrl): self
    {
        return $this->set('base_url', $baseUrl);
    }

    /**
     * Stores the application-wide secret consumed by framework security capabilities.
     */
    public function secret(?string $secret): self
    {
        return $this->set('secret', $secret);
    }

    /**
     * Sets the absolute base path of the application.
     */
    public function basePath(string $basePath): self
    {
        return $this->set('base_path', $basePath);
    }

    /**
     * Sets the current application environment name.
     */
    public function env(string $environment): self
    {
        return $this->set('env', $environment);
    }

    /**
     * Enables or disables debug mode.
     */
    public function debug(bool $debug = true): self
    {
        return $this->set('debug', $debug);
    }

    /**
     * Sets the absolute application source path.
     */
    public function appPath(string $appPath): self
    {
        return $this->set('app_path', $appPath);
    }

    /**
     * Sets the absolute application configuration path.
     */
    public function configPath(string $configPath): self
    {
        return $this->set('config_path', $configPath);
    }

    /**
     * Sets the absolute public web root path.
     */
    public function publicPath(string $publicPath): self
    {
        return $this->set('public_path', $publicPath);
    }

    /**
     * Sets the absolute application storage path.
     */
    public function storagePath(string $storagePath): self
    {
        return $this->set('storage_path', $storagePath);
    }
}
