<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Logging;

use DateTimeImmutable;
use DateTimeInterface;
use JsonException;
use Lemonade\Framework\Core\Logging\Exception\LogStorageException;
use Lemonade\Framework\Filesystem\Contract\DirectoryManagerInterface;
use OverflowException;
use Psr\Log\AbstractLogger;
use Stringable;
use Throwable;
use UnitEnum;

/**
 * Writes private daily JSON-line logs without following symbolic links or weakening filesystem permissions.
 *
 * The containing directory must be a private local directory with mode 0700. Daily files retained or
 * appended by this logger are restricted to mode 0600.
 */
final class RotatingFileLogger extends AbstractLogger
{
    private const int MAX_CONTEXT_DEPTH = 16;

    private bool $directoryReady = false;
    private ?string $rotationDate = null;

    /**
     * Configures one absolute local log path and the number of daily files retained by future writes.
     *
     * The signature remains suitable for direct construction, while framework-managed channels obtain their
     * path from {@see LogFilePathResolver} to enforce storage containment and public-root separation.
     */
    public function __construct(
        private readonly string $file,
        private readonly DirectoryManagerInterface $directoryManager,
        private readonly int $retentionDays = 7,
    ) {
    }

    /**
     * Appends one complete record under an exclusive lock after enforcing directory and file privacy.
     *
     * @throws LogStorageException When the log target cannot be used without crossing its filesystem boundary.
     * @throws JsonException When the normalized record contains a value that JSON cannot represent.
     * @throws OverflowException When context nesting exceeds the supported depth.
     */
    public function log($level, Stringable|string $message, array $context = []): void
    {
        $normalizedContext = $this->normalizeContext($context);
        $levelValue = is_scalar($level) || $level instanceof Stringable ? (string) $level : 'info';

        $record = json_encode([
            'timestamp' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
            'level' => strtolower($levelValue),
            'message' => $this->interpolate((string) $message, $normalizedContext),
            'context' => $normalizedContext,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $this->ensureDirectoryExists();
        $date = date('Y-m-d');
        $currentFile = $this->currentFile($date);

        if ($this->rotationDate !== $date) {
            $this->rotate($currentFile);
            $this->rotationDate = $date;
        }

        $this->append($currentFile, $record . PHP_EOL);
    }

    private function currentFile(string $date): string
    {
        $directory = dirname($this->file);
        $filename = pathinfo($this->file, PATHINFO_FILENAME);
        $extension = pathinfo($this->file, PATHINFO_EXTENSION);
        $suffix = $extension === '' ? '' : '.' . $extension;

        return $directory . DIRECTORY_SEPARATOR . $filename . '-' . $date . $suffix;
    }

    private function ensureDirectoryExists(): void
    {
        if ($this->directoryReady) {
            return;
        }

        $directory = dirname($this->file);

        $this->assertSafePath($this->file);

        try {
            $this->directoryManager->create($directory, 0700);
        } catch (Throwable $exception) {
            throw new LogStorageException(
                sprintf('Log directory "%s" cannot be created securely.', $directory),
                previous: $exception,
            );
        }

        clearstatcache(true, $directory);
        $stat = @lstat($directory);

        if (
            !is_array($stat)
            || ($stat['mode'] & 0170000) !== 0040000
        ) {
            throw new LogStorageException(sprintf(
                'Log directory "%s" must be a non-symbolic directory.',
                $directory,
            ));
        }

        if (($stat['mode'] & 0777) !== 0700 && !@chmod($directory, 0700)) {
            throw new LogStorageException(sprintf(
                'Log directory "%s" permissions cannot be restricted to mode 0700.',
                $directory,
            ));
        }

        $this->directoryReady = true;
    }

    private function rotate(string $currentFile): void
    {
        $directory = dirname($this->file);
        $filename = pathinfo($this->file, PATHINFO_FILENAME);
        $extension = pathinfo($this->file, PATHINFO_EXTENSION);
        $suffix = $extension === '' ? '' : '.' . $extension;
        $pattern = $directory . DIRECTORY_SEPARATOR . $filename . '-*' . $suffix;

        $files = @glob($pattern, GLOB_NOSORT);

        if ($files === false) {
            throw new LogStorageException('Log rotation candidates cannot be enumerated safely.');
        }

        $threshold = time() - (max(1, $this->retentionDays) * 86400);
        $dailyFilenamePattern = sprintf(
            '/^%s-\d{4}-\d{2}-\d{2}%s$/D',
            preg_quote($filename, '/'),
            preg_quote($suffix, '/'),
        );

        foreach ($files as $file) {
            if (
                $file === $currentFile
                || preg_match($dailyFilenamePattern, basename($file)) !== 1
            ) {
                continue;
            }

            clearstatcache(true, $file);
            $stat = @lstat($file);

            if (!is_array($stat)) {
                if (!file_exists($file) && !is_link($file)) {
                    continue;
                }

                throw new LogStorageException(sprintf(
                    'Log file "%s" metadata cannot be inspected.',
                    $file,
                ));
            }

            if (($stat['mode'] & 0170000) !== 0100000) {
                throw new LogStorageException(sprintf(
                    'Log path "%s" must identify a non-symbolic regular file.',
                    $file,
                ));
            }

            if ($stat['mtime'] >= $threshold) {
                if (($stat['mode'] & 0777) !== 0600 && !@chmod($file, 0600)) {
                    throw new LogStorageException(sprintf(
                        'Log file "%s" permissions cannot be restricted to mode 0600.',
                        $file,
                    ));
                }

                continue;
            }

            $handle = @fopen($file, 'r+b');

            if (!is_resource($handle)) {
                if (!file_exists($file) && !is_link($file)) {
                    continue;
                }

                throw new LogStorageException(sprintf(
                    'Expired log file "%s" cannot be opened safely.',
                    $file,
                ));
            }

            $wouldBlock = 0;

            if (!@flock($handle, LOCK_EX | LOCK_NB, $wouldBlock)) {
                fclose($handle);

                if ($wouldBlock === 1) {
                    continue;
                }

                throw new LogStorageException(sprintf(
                    'Expired log file "%s" cannot be locked.',
                    $file,
                ));
            }

            try {
                $openStat = @fstat($handle);

                if (!is_array($openStat)) {
                    throw new LogStorageException(sprintf(
                        'Expired log file "%s" metadata cannot be inspected.',
                        $file,
                    ));
                }

                if ($openStat['mtime'] >= $threshold) {
                    continue;
                }

                if (!@unlink($file) && (file_exists($file) || is_link($file))) {
                    throw new LogStorageException(sprintf(
                        'Expired log file "%s" cannot be removed safely.',
                        $file,
                    ));
                }
            } finally {
                @flock($handle, LOCK_UN);
                fclose($handle);
            }
        }
    }

    private function append(string $path, string $record): void
    {
        clearstatcache(true, $path);

        if (is_link($path)) {
            throw new LogStorageException(sprintf('Log file "%s" cannot be a symbolic link.', $path));
        }

        $handle = @fopen($path, 'ab');

        if (!is_resource($handle)) {
            throw new LogStorageException(sprintf(
                'Log file "%s" cannot be opened or created.',
                $path,
            ));
        }

        if (!@flock($handle, LOCK_EX)) {
            fclose($handle);

            throw new LogStorageException(sprintf('Log file "%s" cannot be locked.', $path));
        }

        try {
            $stat = @fstat($handle);

            if (!is_array($stat) || ($stat['mode'] & 0170000) !== 0100000) {
                throw new LogStorageException(sprintf('Log file "%s" is not a regular file.', $path));
            }

            if (($stat['mode'] & 0777) !== 0600 && !@chmod($path, 0600)) {
                throw new LogStorageException(sprintf(
                    'Log file "%s" permissions cannot be restricted to mode 0600.',
                    $path,
                ));
            }

            $remaining = $record;

            while ($remaining !== '') {
                $written = @fwrite($handle, $remaining);

                if (!is_int($written) || $written <= 0) {
                    throw new LogStorageException(sprintf('Log record cannot be written to "%s".', $path));
                }

                $remaining = substr($remaining, $written);
            }

            if (!@fflush($handle)) {
                throw new LogStorageException(sprintf('Log record cannot be flushed to "%s".', $path));
            }
        } finally {
            @flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function assertSafePath(string $path): void
    {
        $portable = str_replace('\\', '/', $path);
        $segments = explode('/', $portable);
        $filename = basename($portable);

        if (
            $path === ''
            || str_contains($path, "\0")
            || str_contains($path, '://')
            || preg_match('/[\x00-\x1F\x7F]/', $path) === 1
            || preg_match('/[*?\[\]{}]/', $filename) === 1
            || in_array('.', $segments, true)
            || in_array('..', $segments, true)
            || !$this->isAbsolutePath($path)
        ) {
            throw new LogStorageException(
                'Log file must use an absolute local path without traversal or glob metacharacters.',
            );
        }
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\\\')
            || preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function interpolate(string $message, array $context): string
    {
        if ($context === []) {
            return $message;
        }

        $replace = [];

        foreach ($context as $key => $value) {
            if ($value !== null && !is_scalar($value)) {
                continue;
            }

            $replace['{' . $key . '}'] = $value === null ? '' : (string) $value;
        }

        return strtr($message, $replace);
    }

    private function normalize(mixed $value, int $depth = 0): mixed
    {
        if ($value === null || is_scalar($value)) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        if ($value instanceof UnitEnum) {
            return $value instanceof \BackedEnum
                ? $value->value
                : $value->name;
        }

        if ($value instanceof Throwable) {
            return [
                'class' => $value::class,
                'message' => $value->getMessage(),
                'file' => $value->getFile(),
                'line' => $value->getLine(),
                'trace' => $value->getTraceAsString(),
            ];
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        if (is_array($value)) {
            if ($depth >= self::MAX_CONTEXT_DEPTH) {
                throw new OverflowException(sprintf(
                    'Log context exceeds the maximum depth of %d.',
                    self::MAX_CONTEXT_DEPTH,
                ));
            }

            $normalized = [];

            foreach ($value as $key => $item) {
                $normalized[$key] = $this->normalize($item, $depth + 1);
            }

            return $normalized;
        }

        if (is_object($value)) {
            return [
                'class' => $value::class,
            ];
        }

        if (is_resource($value)) {
            return [
                'resource' => get_resource_type($value),
            ];
        }

        return get_debug_type($value);
    }

    /**
     * @param array<mixed> $context
     * @return array<string, mixed>
     */
    private function normalizeContext(array $context): array
    {
        $normalized = [];

        foreach ($context as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $this->normalize($value);
            }
        }

        return $normalized;
    }
}
