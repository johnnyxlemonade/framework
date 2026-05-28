<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Debug\Dump;

use Lemonade\Framework\Debug\Dump\DumpOptions;
use Lemonade\Framework\Debug\Dump\Inspector\NativeValueInspector;
use Lemonade\Framework\Debug\Dump\Model\DumpType;
use PHPUnit\Framework\TestCase;

final class NativeValueInspectorTest extends TestCase
{
    public function testInspectScalarValues(): void
    {
        $inspector = new NativeValueInspector();
        $options = DumpOptions::defaults();

        $string = $inspector->inspect('hello', $options);
        self::assertSame(DumpType::STRING, $string->type());
        self::assertSame('string', $string->label());
        self::assertSame('hello', $string->value());
        self::assertSame(['length' => 5], $string->meta());

        $integer = $inspector->inspect(123, $options);
        self::assertSame(DumpType::INT, $integer->type());
        self::assertSame('123', $integer->value());

        $boolean = $inspector->inspect(true, $options);
        self::assertSame(DumpType::BOOL, $boolean->type());
        self::assertSame('true', $boolean->value());

        $null = $inspector->inspect(null, $options);
        self::assertSame(DumpType::NULL, $null->type());
        self::assertSame('null', $null->value());
    }

    public function testInspectArrayUsesEntryNodes(): void
    {
        $node = (new NativeValueInspector())->inspect(
            ['name' => 'Jan'],
            DumpOptions::defaults(),
        );

        self::assertSame(DumpType::ARRAY, $node->type());
        self::assertSame('array(1)', $node->value());
        self::assertCount(1, $node->children());

        $entry = $node->children()[0];
        self::assertSame(DumpType::ENTRY, $entry->type());
        self::assertSame('name', $entry->label());
        self::assertCount(1, $entry->children());

        $value = $entry->children()[0];
        self::assertSame(DumpType::STRING, $value->type());
        self::assertSame('Jan', $value->value());
    }

    public function testInspectObjectUsesPropertyNodes(): void
    {
        $object = new InspectableObject('public-value', 'protected-value', 'private-value');

        self::assertSame('protected-value', $object->protectedValue());
        self::assertSame('private-value', $object->privateValue());

        $node = (new NativeValueInspector())->inspect(
            $object,
            DumpOptions::defaults(),
        );

        self::assertSame(DumpType::OBJECT, $node->type());
        self::assertSame(InspectableObject::class, $node->label());

        $labels = array_map(
            static fn($child): string => $child->label(),
            $node->children(),
        );

        self::assertContains('+publicValue', $labels);
        self::assertContains('#protectedValue', $labels);
        self::assertContains('-privateValue', $labels);

        foreach ($node->children() as $child) {
            self::assertSame(DumpType::PROPERTY, $child->type());
        }
    }

    public function testDepthLimitUsesDedicatedNodeType(): void
    {
        $node = (new NativeValueInspector())->inspect(
            ['nested' => ['value' => 'too deep']],
            new DumpOptions(maxDepth: 1),
        );

        $depthLimited = $node->children()[0]->children()[0];

        self::assertSame(DumpType::DEPTH_LIMIT, $depthLimited->type());
        self::assertSame('max_depth', $depthLimited->label());
        self::assertTrue($depthLimited->isTruncated());
    }

    public function testMaxItemsTruncatesArrayChildren(): void
    {
        $node = (new NativeValueInspector())->inspect(
            ['a' => 1, 'b' => 2, 'c' => 3],
            new DumpOptions(maxItems: 2),
        );

        self::assertCount(2, $node->children());
        self::assertTrue($node->isTruncated());
    }

    public function testCircularObjectReferenceIsDetected(): void
    {
        $node = new CircularInspectableObject();
        $node->setSelf($node);

        self::assertSame($node, $node->self());

        $dumpNode = (new NativeValueInspector())->inspect(
            $node,
            new DumpOptions(maxDepth: 10),
        );

        $selfProperty = null;

        foreach ($dumpNode->children() as $child) {
            if ($child->label() === '-self') {
                $selfProperty = $child;
                break;
            }
        }

        self::assertNotNull($selfProperty);

        $childNode = $selfProperty->children()[0];

        self::assertSame(DumpType::OBJECT, $childNode->type());
        self::assertTrue($childNode->isCircular());
    }
}

final class InspectableObject
{
    public function __construct(
        public string $publicValue,
        protected string $protectedValue,
        private string $privateValue,
    ) {}

    public function protectedValue(): string
    {
        return $this->protectedValue;
    }

    public function privateValue(): string
    {
        return $this->privateValue;
    }
}

final class CircularInspectableObject
{
    private ?self $self = null;

    public function setSelf(self $self): void
    {
        $this->self = $self;
    }

    public function self(): ?self
    {
        return $this->self;
    }
}
