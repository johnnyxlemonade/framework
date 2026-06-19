<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Logging\Config;

use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;

final class LoggingConfigDefinition extends AbstractConfigDefinition
{
    public static function create(): self
    {
        return new self();
    }

    public static function moduleKey(): string
    {
        return 'logging';
    }

    public function appEnabled(bool $enabled = true): self
    {
        return $this->set('app.enabled', $enabled);
    }

    public function appPath(string $path): self
    {
        return $this->set('app.path', $path);
    }

    public function appLevel(string $level): self
    {
        return $this->set('app.level', $level);
    }

    public function appDays(int $days): self
    {
        return $this->set('app.days', $days);
    }

    public function errorEnabled(bool $enabled = true): self
    {
        return $this->set('error.enabled', $enabled);
    }

    public function errorPath(string $path): self
    {
        return $this->set('error.path', $path);
    }

    public function errorLevel(string $level): self
    {
        return $this->set('error.level', $level);
    }

    public function errorDays(int $days): self
    {
        return $this->set('error.days', $days);
    }

    public function errorLogNotFound(bool $enabled = true): self
    {
        return $this->set('error.not_found', $enabled);
    }

    public function requestEnabled(bool $enabled = true): self
    {
        return $this->set('request.enabled', $enabled);
    }

    public function requestPath(string $path): self
    {
        return $this->set('request.path', $path);
    }

    public function requestLevel(string $level): self
    {
        return $this->set('request.level', $level);
    }

    public function requestDays(int $days): self
    {
        return $this->set('request.days', $days);
    }

    public function requestMinStatus(int $statusCode): self
    {
        return $this->set('request.min_status', $statusCode);
    }

    public function benchmarkEnabled(bool $enabled = true): self
    {
        return $this->set('benchmark.enabled', $enabled);
    }

    public function benchmarkPath(string $path): self
    {
        return $this->set('benchmark.path', $path);
    }

    public function benchmarkLevel(string $level): self
    {
        return $this->set('benchmark.level', $level);
    }

    public function benchmarkDays(int $days): self
    {
        return $this->set('benchmark.days', $days);
    }
}
