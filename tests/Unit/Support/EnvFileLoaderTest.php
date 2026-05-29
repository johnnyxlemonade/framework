<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Support;

use Lemonade\Framework\Support\EnvFileLoader;
use PHPUnit\Framework\TestCase;

final class EnvFileLoaderTest extends TestCase
{
    /**
     * @var array<mixed>
     */
    private array $envBackup = [];

    /**
     * @var array<mixed>
     */
    private array $serverBackup = [];

    /**
     * @var array<string, string|false>
     */
    private array $getenvBackup = [];
    /**
     * @var list<string>
     */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        $this->envBackup = $_ENV;
        $this->serverBackup = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_ENV = $this->envBackup;
        $_SERVER = $this->serverBackup;

        foreach ($this->getenvBackup as $key => $value) {
            if ($value === false) {
                putenv($key);
                continue;
            }

            putenv($key . '=' . $value);
        }

        $this->getenvBackup = [];

        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        $this->tempFiles = [];
    }

    public function testInlineCommentsAreStrippedForUnquotedValues(): void
    {
        $path = $this->createEnvFile(implode(PHP_EOL, [
            'APP_DEBUG=true # debug comment',
            'APP_ENABLED=false # disable comment',
            'APP_NAME=Lemonade # app name comment',
            'APP_EMPTY= # empty stays empty',
            '',
        ]));

        (new EnvFileLoader())->load($path);

        self::assertSame('true', $_ENV['APP_DEBUG']);
        self::assertSame('false', $_ENV['APP_ENABLED']);
        self::assertSame('Lemonade', $_ENV['APP_NAME']);
        self::assertSame('', $_ENV['APP_EMPTY']);
        self::assertTrue(filter_var($_ENV['APP_DEBUG'], FILTER_VALIDATE_BOOLEAN));
        self::assertFalse(filter_var($_ENV['APP_ENABLED'], FILTER_VALIDATE_BOOLEAN));
    }

    public function testHashesInsideQuotedValuesArePreserved(): void
    {
        $path = $this->createEnvFile(implode(PHP_EOL, [
            'APP_NAME_DOUBLE="Lemonade # Framework"',
            "APP_NAME_SINGLE='Lemonade # Framework'",
            '',
        ]));

        (new EnvFileLoader())->load($path);

        self::assertSame('Lemonade # Framework', $_ENV['APP_NAME_DOUBLE']);
        self::assertSame('Lemonade # Framework', $_ENV['APP_NAME_SINGLE']);
    }

    public function testInlineCommentWithLeadingWhitespaceValueBecomesEmpty(): void
    {
        $path = $this->createEnvFile('APP_BLANK=    # only comment');

        (new EnvFileLoader())->load($path);

        self::assertSame('', $_ENV['APP_BLANK']);
    }

    private function createEnvFile(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'env-loader-test-');
        self::assertNotFalse($path);
        $this->tempFiles[] = $path;

        file_put_contents($path, $content);

        $this->backupGetenvKey('APP_DEBUG');
        $this->backupGetenvKey('APP_ENABLED');
        $this->backupGetenvKey('APP_NAME');
        $this->backupGetenvKey('APP_EMPTY');
        $this->backupGetenvKey('APP_NAME_DOUBLE');
        $this->backupGetenvKey('APP_NAME_SINGLE');
        $this->backupGetenvKey('APP_BLANK');

        unset(
            $_ENV['APP_DEBUG'],
            $_ENV['APP_ENABLED'],
            $_ENV['APP_NAME'],
            $_ENV['APP_EMPTY'],
            $_ENV['APP_NAME_DOUBLE'],
            $_ENV['APP_NAME_SINGLE'],
            $_ENV['APP_BLANK'],
            $_SERVER['APP_DEBUG'],
            $_SERVER['APP_ENABLED'],
            $_SERVER['APP_NAME'],
            $_SERVER['APP_EMPTY'],
            $_SERVER['APP_NAME_DOUBLE'],
            $_SERVER['APP_NAME_SINGLE'],
            $_SERVER['APP_BLANK'],
        );

        putenv('APP_DEBUG');
        putenv('APP_ENABLED');
        putenv('APP_NAME');
        putenv('APP_EMPTY');
        putenv('APP_NAME_DOUBLE');
        putenv('APP_NAME_SINGLE');
        putenv('APP_BLANK');

        return $path;
    }

    private function backupGetenvKey(string $key): void
    {
        if (!array_key_exists($key, $this->getenvBackup)) {
            $this->getenvBackup[$key] = getenv($key);
        }
    }
}
