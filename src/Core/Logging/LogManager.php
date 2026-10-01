<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Logging;

use Lemonade\Framework\Core\Logging\Config\LoggingConfig;
use Lemonade\Framework\Filesystem\Contract\DirectoryManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Provides framework-owned app, error, request and benchmark log channels.
 */
final class LogManager
{
    /**
     * @var array<string, LoggerInterface>
     */
    private array $loggers = [];

    /**
     * Initializes cached built-in loggers using canonical files and shared retention.
     */
    public function __construct(
        private readonly LoggingConfig $config,
        private readonly LogFilePathResolver $pathResolver,
        private readonly DirectoryManagerInterface $directoryManager,
    ) {
    }

    /**
     * Returns the always-available runtime exception channel.
     */
    public function error(): LoggerInterface
    {
        return $this->logger('error');
    }

    /**
     * Returns the always-available application channel bound as the PSR logger.
     */
    public function app(): LoggerInterface
    {
        return $this->logger('app');
    }

    /**
     * Returns the optional HTTP request channel or a null logger when disabled.
     */
    public function request(): LoggerInterface
    {
        return $this->logger('request');
    }

    /**
     * Returns the optional benchmark file channel or a null logger when disabled.
     */
    public function benchmark(): LoggerInterface
    {
        return $this->logger('benchmark');
    }

    /**
     * Reports whether a built-in channel writes records under the active policy.
     */
    public function enabled(string $channel, bool $default = false): bool
    {
        return match ($channel) {
            'app', 'error' => true,
            'request' => $this->config->requestEnabled,
            'benchmark' => $this->config->benchmarkEnabled,
            default => $default,
        };
    }

    private function logger(string $channel): LoggerInterface
    {
        if (isset($this->loggers[$channel])) {
            return $this->loggers[$channel];
        }

        if (!$this->enabled($channel)) {
            return $this->loggers[$channel] = new NullLogger();
        }

        return $this->loggers[$channel] = new RotatingFileLogger(
            file: $this->pathResolver->resolve($channel),
            directoryManager: $this->directoryManager,
            retentionDays: $this->config->retentionDays,
        );
    }
}
