<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Diagnostics;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Logging\Config\LoggingConfig;
use Lemonade\Framework\Core\Logging\LogManager;
use Lemonade\Framework\Core\Logging\RotatingFileLogger;
use Lemonade\Framework\Filesystem\Contract\DirectoryManagerInterface;
use Throwable;

/**
 * Records uncaught runtime and PHP diagnostics through the error channel, including bootstrap fallback.
 */
final class ExceptionLogger
{
    /**
     * Binds diagnostic reporting to the root container and canonical application paths.
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ApplicationContext $context,
    ) {
    }

    /**
     * Records an uncaught throwable without allowing logging failures to alter error handling.
     */
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

    /**
     * Records a PHP diagnostic with its severity and source location.
     */
    public function logPhpDiagnostic(
        int $severity,
        string $message,
        string $file,
        int $line,
        string $source,
    ): void {
        try {
            if ($this->container->has(LogManager::class)) {
                $this->container
                    ->get(LogManager::class)
                    ->error()
                    ->error($message, [
                        'severity' => $severity,
                        'file' => $file,
                        'line' => $line,
                        'source' => $source,
                    ]);

                return;
            }
        } catch (Throwable) {
            // LogManager may not be available during early bootstrap failure.
        }

        $this->logFallback(
            new \ErrorException($message, 0, $severity, $file, $line),
            $source,
        );
    }

    private function logFallback(Throwable $exception, string $source): void
    {
        try {
            $config = $this->container->has(LoggingConfig::class)
                ? $this->container->get(LoggingConfig::class)
                : null;

            if ($config instanceof LoggingConfig) {
                $days = $config->retentionDays;
            } else {
                $days = 7;
            }

            (new RotatingFileLogger(
                file: $this->context->resolveLogPath('error.log'),
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
