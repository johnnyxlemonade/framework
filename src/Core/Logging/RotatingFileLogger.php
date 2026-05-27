<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Logging;

use DateTimeImmutable;
use DateTimeInterface;
use JsonException;
use Lemonade\Framework\Filesystem\Contract\DirectoryManagerInterface;
use Psr\Log\AbstractLogger;
use Stringable;
use Throwable;
use UnitEnum;

final class RotatingFileLogger extends AbstractLogger
{
    public function __construct(
        private readonly string $file,
        private readonly DirectoryManagerInterface $directoryManager,
        private readonly int $retentionDays = 7,
    ) {}

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

        file_put_contents(
            $this->currentFile(),
            $this->encode($record) . PHP_EOL,
            FILE_APPEND | LOCK_EX,
        );
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

        if (is_dir($directory)) {
            return;
        }

        $this->directoryManager->create($directory);
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

        $files = glob($pattern);

        if ($files === false) {
            return;
        }

        $threshold = strtotime(sprintf('-%d days', max(1, $this->retentionDays)));

        if ($threshold === false) {
            return;
        }

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $modifiedAt = filemtime($file);

            if ($modifiedAt !== false && $modifiedAt < $threshold) {
                $this->directoryManager->delete($file);
            }
        }
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
