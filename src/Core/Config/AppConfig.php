<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Config;

/**
 * Carries application-wide runtime values resolved from host configuration and bootstrap context.
 */
final readonly class AppConfig
{
    /**
     * Captures immutable host configuration consumed by framework capabilities.
     */
    public function __construct(
        private ?string $timezone,
        private ?string $baseUrl,
        private string $basePath,
        private string $publicPath,
        private string $env,
        private bool $debug,
        private string $appPath,
        private string $configPath,
        private string $storagePath,
        private ?string $secret = null,
    ) {
    }

    /**
     * Returns the configured timezone identifier or null to use the runtime default.
     */
    public function timezone(): ?string
    {
        return $this->timezone;
    }

    /**
     * Returns the explicit application base URL or null when it must be derived from the request.
     */
    public function baseUrl(): ?string
    {
        return $this->baseUrl;
    }

    /**
     * Returns the absolute application root path established during bootstrap.
     */
    public function basePath(): string
    {
        return $this->basePath;
    }

    /**
     * Returns the absolute path exposed by the web server.
     */
    public function publicPath(): string
    {
        return $this->publicPath;
    }

    /**
     * Returns the resolved application environment name.
     */
    public function env(): string
    {
        return $this->env;
    }

    /**
     * Reports whether framework debug behavior is enabled.
     */
    public function debug(): bool
    {
        return $this->debug;
    }

    /**
     * Returns the absolute path containing application source code.
     */
    public function appPath(): string
    {
        return $this->appPath;
    }

    /**
     * Returns the absolute path containing application configuration.
     */
    public function configPath(): string
    {
        return $this->configPath;
    }

    /**
     * Returns the absolute path allocated to writable application data.
     */
    public function storagePath(): string
    {
        return $this->storagePath;
    }

    /**
     * Returns the application secret or null when no secret was configured.
     */
    public function secret(): ?string
    {
        return $this->secret;
    }
}
