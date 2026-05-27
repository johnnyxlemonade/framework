<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Core;

use Lemonade\Framework\Core\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function testGetSupportsDotNotation(): void
    {
        $config = new Config([
            'app' => [
                'name' => 'Lemonade',
            ],
        ]);

        self::assertSame('Lemonade', $config->get('app.name'));
    }

    public function testGetReturnsDefaultForMissingKey(): void
    {
        $config = new Config();

        self::assertSame('default', $config->get('missing.key', 'default'));
    }

    public function testSetSetsNestedValue(): void
    {
        $config = new Config();
        $config->set('app.debug.enabled', true);

        self::assertTrue($config->get('app.debug.enabled'));
    }

    public function testMergeKeepsDefaultRecursiveBehaviorForRegularKeys(): void
    {
        $config = new Config([
            'db' => [
                'options' => [
                    'timeout' => 10,
                    'retries' => 3,
                ],
            ],
        ]);

        $config->merge([
            'db' => [
                'options' => [
                    'timeout' => 30,
                ],
            ],
        ]);

        self::assertSame(
            ['timeout' => 30, 'retries' => 3],
            $config->get('db.options'),
        );
    }

    public function testMergeReplacesFrameworkProvidersAsWholeList(): void
    {
        $config = new Config([
            'framework' => [
                'providers' => ['A', 'B', 'C'],
            ],
        ]);

        $config->merge([
            'framework' => [
                'providers' => ['X'],
            ],
        ]);

        self::assertSame(['X'], $config->get('framework.providers'));
    }

    public function testStringReturnsStringValue(): void
    {
        $config = new Config(['app' => ['name' => 'Lemonade']]);

        self::assertSame('Lemonade', $config->string('app.name'));
    }

    public function testStringReturnsDefaultForMissingValue(): void
    {
        $config = new Config();

        self::assertSame('fallback', $config->string('app.name', 'fallback'));
    }

    public function testIntReturnsIntValue(): void
    {
        $config = new Config(['app' => ['ttl' => 15]]);

        self::assertSame(15, $config->int('app.ttl'));
    }

    public function testIntConvertsNumericString(): void
    {
        $config = new Config(['app' => ['ttl' => '42']]);

        self::assertSame(42, $config->int('app.ttl'));
    }

    public function testBoolReturnsBoolValue(): void
    {
        $config = new Config(['app' => ['debug' => true]]);

        self::assertTrue($config->bool('app.debug'));
    }

    public function testBoolRespectsDefault(): void
    {
        $config = new Config();

        self::assertTrue($config->bool('app.debug', true));
    }

    public function testArrayReturnsArrayValue(): void
    {
        $config = new Config(['app' => ['modules' => ['core', 'cache']]]);

        self::assertSame(['core', 'cache'], $config->array('app.modules'));
    }

    public function testArrayReturnsDefaultForMissingKey(): void
    {
        $config = new Config();

        self::assertSame(['default'], $config->array('app.modules', ['default']));
    }
}
