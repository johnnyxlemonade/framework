<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Config;

use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;

final class CorsConfigDefinition extends AbstractConfigDefinition
{
    public static function create(): self
    {
        return new self();
    }

    public static function moduleKey(): string
    {
        return 'cors';
    }

    public function enabled(bool $enabled = true): self
    {
        return $this->set('enabled', $enabled);
    }

    public function disabled(): self
    {
        return $this->enabled(false);
    }

    /** @param list<string|int|float|bool> $origins */
    public function allowedOrigins(array $origins): self
    {
        return $this->set('allowed_origins', array_values($origins));
    }

    /** @param list<string|int|float|bool> $methods */
    public function allowedMethods(array $methods): self
    {
        return $this->set('allowed_methods', array_values($methods));
    }

    /** @param list<string|int|float|bool> $headers */
    public function allowedHeaders(array $headers): self
    {
        return $this->set('allowed_headers', array_values($headers));
    }

    /** @param list<string|int|float|bool> $headers */
    public function exposedHeaders(array $headers): self
    {
        return $this->set('exposed_headers', array_values($headers));
    }

    public function allowCredentials(bool $allowCredentials = true): self
    {
        return $this->set('allow_credentials', $allowCredentials);
    }

    public function maxAge(?int $maxAge): self
    {
        return $this->set('max_age', $maxAge);
    }
}
