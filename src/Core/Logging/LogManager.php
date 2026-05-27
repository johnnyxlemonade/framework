<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Logging;

use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Filesystem\Contract\DirectoryManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class LogManager
{
    /**
     * @var array<string, LoggerInterface>
     */
    private array $loggers = [];

    public function __construct(
        private readonly Config $config,
        private readonly LogFilePathResolver $pathResolver,
        private readonly DirectoryManagerInterface $directoryManager,
    ) {}

    public function error(): LoggerInterface
    {
        return $this->logger(
            channel: 'error',
            defaultFile: 'error.log',
            enabledDefault: true,
        );
    }

    public function app(): LoggerInterface
    {
        return $this->logger(
            channel: 'app',
            defaultFile: 'app.log',
            enabledDefault: true,
        );
    }

    public function request(): LoggerInterface
    {
        return $this->logger(
            channel: 'request',
            defaultFile: 'request.log',
            enabledDefault: false,
        );
    }

    public function benchmark(): LoggerInterface
    {
        return $this->logger(
            channel: 'benchmark',
            defaultFile: 'benchmark.log',
            enabledDefault: false,
        );
    }

    public function enabled(string $channel, bool $default = false): bool
    {
        return $this->config->bool($channel . '.log.enabled', $default);
    }

    private function logger(
        string $channel,
        string $defaultFile,
        bool $enabledDefault,
    ): LoggerInterface {
        if (isset($this->loggers[$channel])) {
            return $this->loggers[$channel];
        }

        if (!$this->enabled($channel, $enabledDefault)) {
            return $this->loggers[$channel] = new NullLogger();
        }

        $file = $this->config->string($channel . '.log.file', $defaultFile) ?? $defaultFile;

        $days = $this->config->int($channel . '.log.days', 7);

        return $this->loggers[$channel] = new RotatingFileLogger(
            file: $this->pathResolver->resolve($file, $defaultFile),
            directoryManager: $this->directoryManager,
            retentionDays: $days,
        );
    }
}
