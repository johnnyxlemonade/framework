<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Logging;

use DateTimeImmutable;
use DateTimeInterface;
use JsonException;
use Lemonade\Framework\Core\Logging\Exception\LogStorageException;
use Lemonade\Framework\Filesystem\Contract\DirectoryManagerInterface;
use Psr\Log\AbstractLogger;
use Stringable;
use Throwable;
use UnitEnum;

/**
 * Writes private daily JSON-line logs without following symbolic links or weakening filesystem permissions.
 *
 * The containing directory must be a private local directory with mode 0700. Existing daily files are
 * restricted to mode 0600 while locked before they are appended to or considered for rotation.
 */
final class RotatingFileLogger extends AbstractLogger
{
    private const int CREATE_ATTEMPTS = 5;

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
     */
    public function log($level, Stringable|string $message, array $context = []): void
    {
        $this->ensureDirectoryExists();
        $this->rotate();
        $normalizedContext = $this->normalizeContextArray($context);
        $levelValue = is_scalar($level) || $level instanceof Stringable ? (string) $level : 'info';

        $record = [
            'timestamp' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
            'level' => strtolower($levelValue),
            'message' => $this->interpolate((string) $message, $normalizedContext),
            'context' => $this->normalize($normalizedContext),
        ];

        $this->append($this->encode($record) . PHP_EOL);
    }

    private function currentFile(): string
    {
        $directory = dirname($this->file);
        $filename = pathinfo($this->file, PATHINFO_FILENAME);
        $extension = pathinfo($this->file, PATHINFO_EXTENSION);
        $date = date('Y-m-d');

        if ($extension === '') {
            return $directory . DIRECTORY_SEPARATOR . $filename . '-' . $date;
        }

        return $directory . DIRECTORY_SEPARATOR . $filename . '-' . $date . '.' . $extension;
    }

    private function ensureDirectoryExists(): void
    {
        $directory = dirname($this->file);

        $this->assertSafePath($this->file);

        if (!is_dir($directory)) {
            try {
                $this->directoryManager->create($directory, 0700);
            } catch (Throwable $exception) {
                throw new LogStorageException(
                    sprintf('Log directory "%s" cannot be created securely.', $directory),
                    previous: $exception,
                );
            }
        }

        clearstatcache(true, $directory);
        $stat = @lstat($directory);

        if (
            !is_array($stat)
            || is_link($directory)
            || ($stat['mode'] & 0170000) !== 0040000
            || ($stat['mode'] & 0777) !== 0700
        ) {
            throw new LogStorageException(sprintf(
                'Log directory "%s" must be a non-symbolic directory with mode 0700.',
                $directory,
            ));
        }
    }

    private function rotate(): void
    {
        $directory = dirname($this->file);

        if (!is_dir($directory)) {
            return;
        }

        $filename = pathinfo($this->file, PATHINFO_FILENAME);
        $extension = pathinfo($this->file, PATHINFO_EXTENSION);

        $pattern = $extension === ''
            ? $directory . DIRECTORY_SEPARATOR . $filename . '-*'
            : $directory . DIRECTORY_SEPARATOR . $filename . '-*.' . $extension;

        $files = glob($pattern, GLOB_NOSORT);

        if ($files === false) {
            throw new LogStorageException('Log rotation candidates cannot be enumerated safely.');
        }

        $threshold = strtotime(sprintf('-%d days', max(1, $this->retentionDays)));

        if ($threshold === false) {
            throw new LogStorageException('Log retention threshold cannot be calculated.');
        }

        foreach ($files as $file) {
            $handle = $this->openExistingFile($file, false);

            if ($handle === null) {
                continue;
            }

            try {
                $stat = @fstat($handle);

                if (!is_array($stat)) {
                    throw new LogStorageException(sprintf(
                        'Log file "%s" metadata cannot be inspected.',
                        $file,
                    ));
                }

                if ($stat['mtime'] >= $threshold) {
                    continue;
                }

                $this->assertOpenFileIdentity($file, $stat);

                if (!@unlink($file)) {
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

    private function append(string $record): void
    {
        $path = $this->currentFile();
        $handle = $this->openCurrentFile($path);
        $initialSize = null;

        try {
            if (fseek($handle, 0, SEEK_END) !== 0) {
                throw new LogStorageException(sprintf('Log file "%s" cannot be positioned safely.', $path));
            }

            $position = ftell($handle);

            if (!is_int($position) || $position < 0) {
                throw new LogStorageException(sprintf('Log file "%s" size cannot be determined.', $path));
            }

            $initialSize = $position;
            $remaining = $record;

            while ($remaining !== '') {
                $written = @fwrite($handle, $remaining);

                if (!is_int($written) || $written <= 0) {
                    throw new LogStorageException(sprintf('Log record cannot be written to "%s".', $path));
                }

                $remaining = substr($remaining, $written);
            }

            if (!fflush($handle)) {
                throw new LogStorageException(sprintf('Log record cannot be flushed to "%s".', $path));
            }
        } catch (Throwable $exception) {
            if (is_int($initialSize)) {
                @ftruncate($handle, $initialSize);
                @fflush($handle);
            }

            if ($exception instanceof LogStorageException) {
                throw $exception;
            }

            throw new LogStorageException(sprintf(
                'Log record cannot be persisted to "%s".',
                $path,
            ), previous: $exception);
        } finally {
            @flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * @return resource
     */
    private function openCurrentFile(string $path)
    {
        for ($attempt = 0; $attempt < self::CREATE_ATTEMPTS; ++$attempt) {
            clearstatcache(true, $path);
            $existing = @lstat($path);

            if (is_array($existing)) {
                $handle = $this->openExistingFile($path, true);

                if ($handle !== null) {
                    return $handle;
                }

                continue;
            }

            if (is_link($path)) {
                throw new LogStorageException(sprintf('Log file "%s" cannot be a symbolic link.', $path));
            }

            $handle = @fopen($path, 'x+b');

            if (!is_resource($handle)) {
                continue;
            }

            if (!flock($handle, LOCK_EX)) {
                fclose($handle);

                throw new LogStorageException(sprintf('Log file "%s" cannot be locked.', $path));
            }

            try {
                $this->secureOpenFile($path, $handle);

                return $handle;
            } catch (Throwable $exception) {
                $this->removeCreatedFileIfOwned($path, $handle);
                @flock($handle, LOCK_UN);
                fclose($handle);

                throw $exception;
            }
        }

        throw new LogStorageException(sprintf(
            'Log file "%s" cannot be opened without a filesystem race.',
            $path,
        ));
    }

    /**
     * @return resource|null
     */
    private function openExistingFile(string $path, bool $blocking)
    {
        clearstatcache(true, $path);
        $before = @lstat($path);

        if (!is_array($before)) {
            return null;
        }

        if (is_link($path) || ($before['mode'] & 0170000) !== 0100000) {
            throw new LogStorageException(sprintf(
                'Log path "%s" must identify a non-symbolic regular file.',
                $path,
            ));
        }

        $handle = @fopen($path, 'r+b');

        if (!is_resource($handle)) {
            throw new LogStorageException(sprintf('Log file "%s" cannot be opened securely.', $path));
        }

        $lockOperation = LOCK_EX | ($blocking ? 0 : LOCK_NB);

        if (!flock($handle, $lockOperation)) {
            fclose($handle);

            return null;
        }

        try {
            $this->secureOpenFile($path, $handle);

            return $handle;
        } catch (Throwable $exception) {
            @flock($handle, LOCK_UN);
            fclose($handle);

            throw $exception;
        }
    }

    /**
     * @param resource $handle
     */
    private function secureOpenFile(string $path, $handle): void
    {
        $stat = @fstat($handle);

        if (!is_array($stat) || ($stat['mode'] & 0170000) !== 0100000) {
            throw new LogStorageException(sprintf('Log file "%s" is not a regular file.', $path));
        }

        $this->assertOpenFileIdentity($path, $stat);

        if (!@chmod($path, 0600)) {
            throw new LogStorageException(sprintf(
                'Log file "%s" permissions cannot be restricted to mode 0600.',
                $path,
            ));
        }

        $secured = @fstat($handle);

        if (!is_array($secured) || ($secured['mode'] & 0777) !== 0600) {
            throw new LogStorageException(sprintf(
                'Log file "%s" permissions remain less restrictive than mode 0600.',
                $path,
            ));
        }

        $this->assertOpenFileIdentity($path, $secured);
    }

    /**
     * @param resource $handle
     */
    private function removeCreatedFileIfOwned(string $path, $handle): void
    {
        $openStat = @fstat($handle);
        clearstatcache(true, $path);
        $pathStat = @lstat($path);

        if (
            !is_array($openStat)
            || !is_array($pathStat)
            || is_link($path)
            || $pathStat['dev'] !== $openStat['dev']
            || $pathStat['ino'] !== $openStat['ino']
        ) {
            return;
        }

        @unlink($path);
    }

    /**
     * @param array<mixed> $openStat
     */
    private function assertOpenFileIdentity(string $path, array $openStat): void
    {
        clearstatcache(true, $path);
        $pathStat = @lstat($path);

        if (
            !is_array($pathStat)
            || is_link($path)
            || ($pathStat['mode'] & 0170000) !== 0100000
            || $pathStat['dev'] !== $openStat['dev']
            || $pathStat['ino'] !== $openStat['ino']
        ) {
            throw new LogStorageException(sprintf(
                'Log file "%s" changed identity during a filesystem operation.',
                $path,
            ));
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

        $cursor = dirname($path);

        while (true) {
            if (is_link($cursor)) {
                throw new LogStorageException('Log directory path cannot contain symbolic links.');
            }

            $parent = dirname($cursor);

            if ($parent === $cursor) {
                return;
            }

            $cursor = $parent;
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
            if (!$this->isInterpolable($value)) {
                continue;
            }

            $replace['{' . $key . '}'] = $this->stringify($value);
        }

        return strtr($message, $replace);
    }

    private function isInterpolable(mixed $value): bool
    {
        return $value === null
            || is_scalar($value)
            || $value instanceof Stringable
            || $value instanceof UnitEnum
            || $value instanceof DateTimeInterface;
    }

    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        if ($value instanceof UnitEnum) {
            return $value instanceof \BackedEnum
                ? (string) $value->value
                : $value->name;
        }

        if (is_scalar($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        return '';
    }

    private function normalize(mixed $value): mixed
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
            $normalized = [];

            foreach ($value as $key => $item) {
                $normalized[$key] = $this->normalize($item);
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
    private function normalizeContextArray(array $context): array
    {
        $normalized = [];

        foreach ($context as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function encode(array $record): string
    {
        try {
            return json_encode(
                $record,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
        } catch (JsonException $exception) {
            $fallback = json_encode([
                'timestamp' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
                'level' => 'error',
                'message' => 'Failed to encode log record.',
                'context' => [
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $fallback !== false
                ? $fallback
                : '{"level":"error","message":"Failed to encode log record."}';
        }
    }
}
