<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Config;

use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;

/**
 * Builds one composable layer of cross-origin resource sharing configuration.
 */
final class CorsConfigDefinition extends AbstractConfigDefinition
{
    /**
     * Creates an empty definition that contributes only explicitly configured values.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Returns the registry key under which CORS definitions are collected.
     */
    public static function moduleKey(): string
    {
        return 'cors';
    }

    /**
     * Sets whether CORS processing participates in the HTTP pipeline.
     */
    public function enabled(bool $enabled = true): self
    {
        return $this->set('enabled', $enabled);
    }

    /**
     * Disables CORS processing for this definition layer.
     */
    public function disabled(): self
    {
        return $this->enabled(false);
    }

    /**
     * Replaces the allowed origin values contributed by this layer.
     *
     * @param list<string|int|float|bool> $origins
     */
    public function allowedOrigins(array $origins): self
    {
        return $this->set('allowed_origins', array_values($origins));
    }

    /**
     * Replaces the allowed preflight method values contributed by this layer.
     *
     * @param list<string|int|float|bool> $methods
     */
    public function allowedMethods(array $methods): self
    {
        return $this->set('allowed_methods', array_values($methods));
    }

    /**
     * Replaces the allowed preflight header values contributed by this layer.
     *
     * @param list<string|int|float|bool> $headers
     */
    public function allowedHeaders(array $headers): self
    {
        return $this->set('allowed_headers', array_values($headers));
    }

    /**
     * Replaces the response headers exposed to cross-origin clients.
     *
     * @param list<string|int|float|bool> $headers
     */
    public function exposedHeaders(array $headers): self
    {
        return $this->set('exposed_headers', array_values($headers));
    }

    /**
     * Sets whether cross-origin responses may expose credentials.
     */
    public function allowCredentials(bool $allowCredentials = true): self
    {
        return $this->set('allow_credentials', $allowCredentials);
    }

    /**
     * Sets the browser preflight cache lifetime, or clears it with null.
     */
    public function maxAge(?int $maxAge): self
    {
        return $this->set('max_age', $maxAge);
    }
}
