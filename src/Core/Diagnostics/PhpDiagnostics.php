<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Diagnostics;

use ErrorException;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Throwable;

/**
 * Installs the framework-wide PHP diagnostic policy for one PHP request.
 *
 * @internal
 */
final class PhpDiagnostics
{
    private static ?self $active = null;
    private static bool $shutdownHandlerRegistered = false;

    private bool $fatalReported = false;
    private bool $errorHandlerInstalled = false;
    private int $installationDepth = 0;

    public function __construct(
        private readonly ApplicationContext $context,
        private readonly ContainerInterface $container,
    ) {
    }

    public function install(): void
    {
        ++$this->installationDepth;
        if ($this->installationDepth > 1) {
            return;
        }

        self::$active = $this;

        error_reporting(E_ALL);
        ini_set('display_errors', $this->context->isDevelopment() ? '1' : '0');
        ini_set('display_startup_errors', $this->context->isDevelopment() ? '1' : '0');

        set_error_handler([self::class, 'handleError']);
        $this->errorHandlerInstalled = true;

        if (self::$shutdownHandlerRegistered) {
            return;
        }

        register_shutdown_function([self::class, 'handleShutdown']);
        self::$shutdownHandlerRegistered = true;
    }

    public function uninstall(): void
    {
        if ($this->installationDepth === 0) {
            return;
        }

        --$this->installationDepth;
        if ($this->installationDepth > 0 || !$this->errorHandlerInstalled) {
            return;
        }

        restore_error_handler();
        $this->errorHandlerInstalled = false;
    }

    public static function handleError(
        int $severity,
        string $message,
        string $file,
        int $line,
    ): bool {
        if (self::$active === null) {
            return false;
        }

        return self::$active->reportError($severity, $message, $file, $line);
    }

    public static function handleShutdown(): void
    {
        if (self::$active === null) {
            return;
        }

        $error = error_get_last();
        if (!is_array($error)) {
            return;
        }

        self::$active->reportShutdownError($error);
    }

    public function reportError(
        int $severity,
        string $message,
        string $file,
        int $line,
    ): bool {
        if (error_reporting() === 0) {
            return false;
        }

        if ($this->throwsDiagnostic($severity)) {
            throw new ErrorException($message, 0, $severity, $file, $line);
        }

        $this->report($severity, $message, $file, $line, 'php.error');

        return true;
    }

    /**
     * @param array{type?: mixed, message?: mixed, file?: mixed, line?: mixed} $error
     */
    public function reportShutdownError(array $error): void
    {
        $severity = is_int($error['type'] ?? null) ? $error['type'] : 0;
        if (!$this->isFatal($severity) || $this->fatalReported) {
            return;
        }

        $this->fatalReported = true;

        $message = is_string($error['message'] ?? null) ? $error['message'] : 'Unknown fatal PHP error.';
        $file = is_string($error['file'] ?? null) ? $error['file'] : 'unknown';
        $line = is_int($error['line'] ?? null) ? $error['line'] : 0;

        $this->report($severity, $message, $file, $line, 'php.shutdown');

        if (headers_sent()) {
            return;
        }

        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8', true);
        echo $this->fatalResponseBody($message, $file, $line);
    }

    public function fatalResponseBody(string $message, string $file, int $line): string
    {
        if (!$this->context->isDevelopment()) {
            return '500 Internal Server Error';
        }

        return sprintf(
            "500 Internal Server Error\n\nFatal PHP error: %s\n%s:%d",
            $message,
            $file,
            $line,
        );
    }

    private function throwsDiagnostic(int $severity): bool
    {
        if ($severity === E_USER_ERROR) {
            return true;
        }

        if (!$this->context->isDevelopment()) {
            return false;
        }

        return in_array($severity, [
            E_ERROR,
            E_RECOVERABLE_ERROR,
            E_WARNING,
            E_USER_WARNING,
        ], true);
    }

    private function isFatal(int $severity): bool
    {
        return in_array($severity, [
            E_ERROR,
            E_PARSE,
            E_CORE_ERROR,
            E_COMPILE_ERROR,
            E_USER_ERROR,
        ], true);
    }

    private function report(
        int $severity,
        string $message,
        string $file,
        int $line,
        string $source,
    ): void {
        try {
            if ($this->container->has(ExceptionLogger::class)) {
                $this->container
                    ->get(ExceptionLogger::class)
                    ->logPhpDiagnostic($severity, $message, $file, $line, $source);

                return;
            }
        } catch (Throwable) {
            // PHP diagnostics must not recursively fail while being reported.
        }

        error_log(sprintf('[Lemonade][PHP] %s in %s:%d (severity %d)', $message, $file, $line, $severity));
    }
}
