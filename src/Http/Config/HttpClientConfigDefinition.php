<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Config;

use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;

final class HttpClientConfigDefinition extends AbstractConfigDefinition
{
    public static function create(): self
    {
        return new self();
    }

    public static function moduleKey(): string
    {
        return 'http_client';
    }

    public function timeout(float|int $seconds): self
    {
        return $this->set('timeout', $seconds);
    }

    public function connectTimeout(float|int $seconds): self
    {
        return $this->set('connect_timeout', $seconds);
    }

    public function verifySsl(bool $verifySsl = true): self
    {
        return $this->set('verify_ssl', $verifySsl);
    }
}
