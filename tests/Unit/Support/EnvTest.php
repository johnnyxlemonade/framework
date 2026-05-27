<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Support;

use Lemonade\Framework\Support\Env;
use PHPUnit\Framework\TestCase;

final class EnvTest extends TestCase
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
    }

    public function testStringReturnsValueFromEnv(): void
    {
        $_ENV['APP_NAME'] = 'Lemonade';

        self::assertSame('Lemonade', Env::string('APP_NAME'));
    }

    public function testStringReturnsDefaultForMissingEmptyAndNonScalar(): void
    {
        unset($_ENV['MISSING']);
        self::assertSame('default', Env::string('MISSING', 'default'));

        $_ENV['EMPTY_VALUE'] = '';
        self::assertSame('default', Env::string('EMPTY_VALUE', 'default'));

        $_ENV['ARRAY_VALUE'] = ['invalid'];
        self::assertSame('default', Env::string('ARRAY_VALUE', 'default'));
    }

    public function testIntReturnsIntNumericStringAndDefaultForInvalid(): void
    {
        $_ENV['INT_VALUE'] = 10;
        $_ENV['INT_STRING'] = '42';
        $_ENV['INVALID_INT'] = 'abc';

        self::assertSame(10, Env::int('INT_VALUE', 1));
        self::assertSame(42, Env::int('INT_STRING', 1));
        self::assertSame(1, Env::int('INVALID_INT', 1));
    }

    public function testFloatReturnsFloatIntNumericStringAndDefaultForInvalid(): void
    {
        $_ENV['FLOAT_VALUE'] = 1.5;
        $_ENV['FLOAT_INT'] = 5;
        $_ENV['FLOAT_STRING'] = '9.25';
        $_ENV['INVALID_FLOAT'] = 'abc';

        self::assertSame(1.5, Env::float('FLOAT_VALUE', 0.1));
        self::assertSame(5.0, Env::float('FLOAT_INT', 0.1));
        self::assertSame(9.25, Env::float('FLOAT_STRING', 0.1));
        self::assertSame(0.1, Env::float('INVALID_FLOAT', 0.1));
    }

    public function testBoolParsesTrueFalseOneZeroYesNoOnOff(): void
    {
        $_ENV['BOOL_TRUE'] = 'true';
        $_ENV['BOOL_FALSE'] = 'false';
        $_ENV['BOOL_ONE'] = '1';
        $_ENV['BOOL_ZERO'] = '0';
        $_ENV['BOOL_YES'] = 'yes';
        $_ENV['BOOL_NO'] = 'no';
        $_ENV['BOOL_ON'] = 'on';
        $_ENV['BOOL_OFF'] = 'off';
        $_ENV['BOOL_INVALID'] = 'maybe';

        self::assertTrue(Env::bool('BOOL_TRUE'));
        self::assertFalse(Env::bool('BOOL_FALSE', true));
        self::assertTrue(Env::bool('BOOL_ONE'));
        self::assertFalse(Env::bool('BOOL_ZERO', true));
        self::assertTrue(Env::bool('BOOL_YES'));
        self::assertFalse(Env::bool('BOOL_NO', true));
        self::assertTrue(Env::bool('BOOL_ON'));
        self::assertFalse(Env::bool('BOOL_OFF', true));
        self::assertTrue(Env::bool('BOOL_INVALID', true));
    }

    public function testListSplitsTrimsRemovesEmptyAndDuplicates(): void
    {
        $_ENV['ALLOWED_HOSTS'] = ' a.com, b.com, , a.com ,c.com ';

        self::assertSame(['a.com', 'b.com', 'c.com'], Env::list('ALLOWED_HOSTS'));
    }

    public function testListReturnsDefaultForMissingAndEmpty(): void
    {
        unset($_ENV['MISSING_LIST']);
        $_ENV['EMPTY_LIST'] = '  ';

        self::assertSame(['default'], Env::list('MISSING_LIST', ['default']));
        self::assertSame(['default'], Env::list('EMPTY_LIST', ['default']));
    }

    public function testStringCanReadFromGetenvWhenEnvAndServerAreMissing(): void
    {
        unset($_ENV['GETENV_ONLY'], $_SERVER['GETENV_ONLY']);
        $this->backupGetenvKey('GETENV_ONLY');
        putenv('GETENV_ONLY=value-from-getenv');

        self::assertSame('value-from-getenv', Env::string('GETENV_ONLY'));
    }

    private function backupGetenvKey(string $key): void
    {
        if (!array_key_exists($key, $this->getenvBackup)) {
            $this->getenvBackup[$key] = getenv($key);
        }
    }
}
