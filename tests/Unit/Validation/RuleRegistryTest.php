<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Validation;

use Lemonade\Framework\Validation\Rule\RuleRegistry;
use Lemonade\Framework\Validation\Rule\ValidationRuleInterface;
use PHPUnit\Framework\TestCase;

final class RuleRegistryTest extends TestCase
{
    public function testHasForKnownAndUnknownRule(): void
    {
        $registry = new RuleRegistry();

        self::assertTrue($registry->has('required'));
        self::assertFalse($registry->has('unknown_rule'));
    }

    public function testGetReturnsRegisteredClassForKnownRuleAndNullForUnknown(): void
    {
        $registry = new RuleRegistry();

        $required = $registry->get('required');

        self::assertIsString($required);
        self::assertTrue(is_subclass_of($required, ValidationRuleInterface::class));
        self::assertNull($registry->get('missing_rule'));
    }

    public function testAddRuleWithClassStringAndInstanceAndTrimmedName(): void
    {
        $registry = new RuleRegistry();

        $registry->addRule(' custom_class ', RegistryAlwaysPassRule::class);
        $registry->addRule('custom_instance', new RegistryAlwaysFailRule());

        self::assertTrue($registry->has('custom_class'));
        self::assertTrue($registry->has('custom_instance'));
        self::assertSame(RegistryAlwaysPassRule::class, $registry->get('custom_class'));
        self::assertInstanceOf(RegistryAlwaysFailRule::class, $registry->get('custom_instance'));
    }

    public function testAddRuleThrowsForInvalidInput(): void
    {
        $registry = new RuleRegistry();

        $this->expectException(\InvalidArgumentException::class);
        $registry->addRule('   ', RegistryAlwaysPassRule::class);
    }

    public function testAddRuleThrowsForNonExistingClass(): void
    {
        $registry = new RuleRegistry();
        $method = new \ReflectionMethod($registry, 'addRule');

        $this->expectException(\InvalidArgumentException::class);
        $method->invoke($registry, 'x', 'Missing\\RuleClass');
    }

    public function testAddRuleThrowsForClassWithoutInterface(): void
    {
        $registry = new RuleRegistry();
        $method = new \ReflectionMethod($registry, 'addRule');

        $this->expectException(\InvalidArgumentException::class);
        $method->invoke($registry, 'x', \stdClass::class);
    }

    public function testAddRuleWithClassStringReplacesPreviouslyRegisteredInstance(): void
    {
        $registry = new RuleRegistry();
        $instance = new RegistryAlwaysFailRule();
        $registry->addRule('custom_replace', $instance);

        self::assertSame($instance, $registry->get('custom_replace'));

        $registry->addRule('custom_replace', RegistryAlwaysPassRule::class);
        self::assertSame(RegistryAlwaysPassRule::class, $registry->get('custom_replace'));
    }
}

final class RegistryAlwaysPassRule implements ValidationRuleInterface
{
    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($value, $param, $data);

        return true;
    }
}

final class RegistryAlwaysFailRule implements ValidationRuleInterface
{
    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($value, $param, $data);

        return false;
    }
}
