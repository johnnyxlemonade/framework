<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Diagnostics;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Logging\LogManager;
use Lemonade\Framework\Core\Logging\RotatingFileLogger;
use Lemonade\Framework\Filesystem\Contract\DirectoryManagerInterface;
use Throwable;

final class ExceptionLogger
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ApplicationContext $context,
    ) {}

    public function log(Throwable $exception, string $source): void
    {
        try {
            if ($this->container->has(LogManager::class)) {
                $this->container
                    ->get(LogManager::class)
                    ->error()
                    ->error($exception->getMessage(), $this->exceptionContext($exception, $source));

                return;
            }
        } catch (Throwable) {
            // LogManager may not be available during early bootstrap failure.
        }

        $this->logFallback($exception, $source);
    }

    private function logFallback(Throwable $exception, string $source): void
    {
        try {
            $config = $this->container->has(Config::class)
                ? $this->container->get(Config::class)
                : null;

            if ($config instanceof Config) {
                $enabled = $config->bool('error.log.enabled', true);

                if (!$enabled) {
                    return;
                }

                $file = $config->string('error.log.file', 'writable/logs/error.log') ?? 'writable/logs/error.log';
                $days = $config->int('error.log.days', 7);
            } else {
                $file = 'writable/logs/error.log';
                $days = 7;
            }

            (new RotatingFileLogger(
                file: $this->resolveLogFile($file),
                directoryManager: $this->container->get(DirectoryManagerInterface::class),
                retentionDays: $days,
            ))->error($exception->getMessage(), [
                ...$this->exceptionContext($exception, $source),
                'logger_fallback' => true,
            ]);
        } catch (Throwable) {
            // Fallback logging must never break exception handling.
        }
    }

    private function resolveLogFile(string $file): string
    {
        if ($this->isAbsolutePath($file)) {
            return $file;
        }

        return $this->context->storagePath($file);
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || preg_match('/^[A-Z]:[\/\\\\]/i', $path) === 1;
    }

    /**
     * @return array{
     *     exception: class-string<Throwable>,
     *     message: string,
     *     file: string,
     *     line: int,
     *     trace: string,
     *     source: string
     * }
     */
    private function exceptionContext(Throwable $exception, string $source): array
    {
        return [
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'source' => $source,
        ];
    }
}
