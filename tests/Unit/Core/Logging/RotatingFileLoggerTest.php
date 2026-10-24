<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Core\Logging;

use DateTimeImmutable;
use JsonException;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Logging\RotatingFileLogger;
use Lemonade\Framework\Filesystem\Manager\DirectoryManager;
use Lemonade\Framework\Image\Value\ResizeMode;
use OverflowException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Stringable;

final class RotatingFileLoggerTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = rtrim(sys_get_temp_dir(), '/\\')
            . DIRECTORY_SEPARATOR
            . 'lemonade-logger-'
            . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->root) || is_link($this->root)) {
            (new DirectoryManager())->delete($this->root);
        }
    }

    public function testWritesInterpolatedNormalizedJsonLine(): void
    {
        $date = new DateTimeImmutable('2026-10-24T10:20:30+02:00');
        $exception = new RuntimeException('generic exception detail');
        $resource = fopen('php://memory', 'r+b');
        $stringable = new class implements Stringable {
            public function __toString(): string
            {
                return 'Lemonade';
            }
        };

        self::assertIsResource($resource);

        try {
            $this->logger()->info(
                '{name} {date} {environment} {mode} {nullable}',
                [
                    'name' => $stringable,
                    'date' => $date,
                    'environment' => Environment::Testing,
                    'mode' => ResizeMode::Contain,
                    'nullable' => null,
                    'nested' => [
                        'enabled' => true,
                        'object' => new \stdClass(),
                    ],
                    'resource' => $resource,
                    'exception' => $exception,
                    0 => 'ignored numeric context key',
                ],
            );
        } finally {
            fclose($resource);
        }

        $record = $this->record();

        self::assertSame('Lemonade 2026-10-24T10:20:30+02:00 test Contain ', $record['message']);
        self::assertSame('Lemonade', $record['context']['name']);
        self::assertSame('2026-10-24T10:20:30+02:00', $record['context']['date']);
        self::assertSame('test', $record['context']['environment']);
        self::assertSame('Contain', $record['context']['mode']);
        self::assertSame(['class' => \stdClass::class], $record['context']['nested']['object']);
        self::assertSame(['resource' => 'stream'], $record['context']['resource']);
        self::assertSame(RuntimeException::class, $record['context']['exception']['class']);
        self::assertSame('generic exception detail', $record['context']['exception']['message']);
        self::assertArrayNotHasKey(0, $record['context']);
    }

    public function testInvalidJsonValueThrowsWithoutWritingFallbackRecord(): void
    {
        try {
            $this->logger()->info('invalid', ['value' => INF]);
            self::fail('An invalid JSON value must fail explicitly.');
        } catch (JsonException) {
            self::assertFileDoesNotExist($this->dailyFile());
        }
    }

    public function testCyclicContextFailsAtBoundedDepth(): void
    {
        $cycle = [];
        $cycle['self'] = &$cycle;

        try {
            $this->logger()->info('cycle', ['cycle' => $cycle]);
            self::fail('A cyclic context must not recurse indefinitely.');
        } catch (OverflowException $exception) {
            self::assertStringContainsString('maximum depth', $exception->getMessage());
            self::assertFileDoesNotExist($this->dailyFile());
        }
    }

    public function testExcessivelyDeepContextFailsAtBoundedDepth(): void
    {
        $value = 'leaf';

        for ($depth = 0; $depth < 17; ++$depth) {
            $value = ['nested' => $value];
        }

        try {
            $this->logger()->info('deep', ['value' => $value]);
            self::fail('An excessively deep context must fail explicitly.');
        } catch (OverflowException $exception) {
            self::assertStringContainsString('maximum depth', $exception->getMessage());
            self::assertFileDoesNotExist($this->dailyFile());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function record(): array
    {
        $contents = file_get_contents($this->dailyFile());

        self::assertIsString($contents);

        $record = json_decode(trim($contents), true, 32, JSON_THROW_ON_ERROR);

        self::assertIsArray($record);

        return $record;
    }

    private function logger(): RotatingFileLogger
    {
        return new RotatingFileLogger(
            $this->root . '/logs/app.log',
            new DirectoryManager(),
        );
    }

    private function dailyFile(): string
    {
        return $this->root . '/logs/app-' . date('Y-m-d') . '.log';
    }
}
