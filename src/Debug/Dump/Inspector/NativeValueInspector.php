<?php

declare(strict_types=1);

namespace Lemonade\Framework\Debug\Dump\Inspector;

use Lemonade\Framework\Debug\Dump\Contract\ValueInspectorInterface;
use Lemonade\Framework\Debug\Dump\DumpOptions;
use Lemonade\Framework\Debug\Dump\Model\DumpNode;
use Lemonade\Framework\Debug\Dump\Model\DumpType;
use ReflectionObject;
use ReflectionProperty;
use Throwable;
use UnitEnum;

final class NativeValueInspector implements ValueInspectorInterface
{
    /**
     * @var array<int, true>
     */
    private array $visitedObjects = [];

    public function inspect(mixed $value, DumpOptions $options): DumpNode
    {
        $this->visitedObjects = [];

        return $this->inspectValue($value, $options, 0);
    }

    private function inspectValue(mixed $value, DumpOptions $options, int $depth): DumpNode
    {
        if ($depth >= $options->maxDepth()) {
            return new DumpNode(
                type: DumpType::DEPTH_LIMIT,
                label: 'max_depth',
                value: '…',
                truncated: true,
            );
        }

        if ($value === null) {
            return new DumpNode(DumpType::NULL, 'null', 'null');
        }

        if (is_bool($value)) {
            return new DumpNode(DumpType::BOOL, 'bool', $value ? 'true' : 'false');
        }

        if (is_int($value)) {
            return new DumpNode(DumpType::INT, 'int', (string) $value);
        }

        if (is_float($value)) {
            return new DumpNode(DumpType::FLOAT, 'float', (string) $value);
        }

        if (is_string($value)) {
            return $this->inspectString($value, $options);
        }

        if (is_array($value)) {
            return $this->inspectArray($value, $options, $depth);
        }

        if (is_object($value)) {
            return $this->inspectObject($value, $options, $depth);
        }

        if (is_resource($value)) {
            return new DumpNode(
                type: DumpType::RESOURCE,
                label: 'resource',
                value: get_resource_type($value),
            );
        }

        return new DumpNode(
            type: DumpType::UNKNOWN,
            label: 'unknown',
            value: get_debug_type($value),
        );
    }

    private function inspectString(string $value, DumpOptions $options): DumpNode
    {
        $length = strlen($value);
        $truncated = $length > $options->maxStringLength();

        if ($truncated) {
            $value = substr($value, 0, $options->maxStringLength()) . '…';
        }

        return new DumpNode(
            type: DumpType::STRING,
            label: 'string',
            value: $value,
            meta: [
                'length' => $length,
            ],
            truncated: $truncated,
        );
    }

    /**
     * @param array<array-key, mixed> $value
     */
    private function inspectArray(array $value, DumpOptions $options, int $depth): DumpNode
    {
        $children = [];
        $count = count($value);
        $index = 0;
        $truncated = false;

        foreach ($value as $key => $item) {
            if ($index >= $options->maxItems()) {
                $truncated = true;
                break;
            }

            $children[] = new DumpNode(
                type: DumpType::ENTRY,
                label: (string) $key,
                children: [$this->inspectValue($item, $options, $depth + 1)],
            );

            $index++;
        }

        return new DumpNode(
            type: DumpType::ARRAY,
            label: 'array',
            value: 'array(' . $count . ')',
            children: $children,
            meta: [
                'count' => $count,
            ],
            truncated: $truncated,
        );
    }

    private function inspectObject(object $value, DumpOptions $options, int $depth): DumpNode
    {
        $objectId = spl_object_id($value);

        if (isset($this->visitedObjects[$objectId])) {
            return new DumpNode(
                type: DumpType::OBJECT,
                label: $value::class,
                value: 'circular reference',
                meta: [
                    'object_id' => $objectId,
                ],
                circular: true,
            );
        }

        $this->visitedObjects[$objectId] = true;

        if ($value instanceof UnitEnum) {
            return new DumpNode(
                type: DumpType::OBJECT,
                label: $value::class,
                value: $value->name,
                meta: [
                    'object_id' => $objectId,
                    'enum' => true,
                ],
            );
        }

        try {
            $reflection = new ReflectionObject($value);
            $properties = $reflection->getProperties();

            $children = [];
            $index = 0;
            $truncated = false;

            foreach ($properties as $property) {
                if (!$this->shouldShowProperty($property, $options)) {
                    continue;
                }

                if ($index >= $options->maxItems()) {
                    $truncated = true;
                    break;
                }

                $property->setAccessible(true);

                $children[] = new DumpNode(
                    type: DumpType::PROPERTY,
                    label: $this->propertyLabel($property),
                    children: [
                        $this->inspectValue($this->readProperty($property, $value), $options, $depth + 1),
                    ],
                );

                $index++;
            }

            return new DumpNode(
                type: DumpType::OBJECT,
                label: $value::class,
                value: $options->showObjectIds() ? 'object#' . $objectId : 'object',
                children: $children,
                meta: [
                    'class' => $value::class,
                    'object_id' => $objectId,
                    'property_count' => count($properties),
                ],
                truncated: $truncated,
            );
        } catch (Throwable $exception) {
            return new DumpNode(
                type: DumpType::OBJECT,
                label: $value::class,
                value: 'inspection failed: ' . $exception->getMessage(),
                meta: [
                    'class' => $value::class,
                    'object_id' => $objectId,
                ],
            );
        }
    }

    private function shouldShowProperty(ReflectionProperty $property, DumpOptions $options): bool
    {
        if ($property->isPrivate()) {
            return $options->showPrivateProperties();
        }

        if ($property->isProtected()) {
            return $options->showProtectedProperties();
        }

        return true;
    }

    private function propertyLabel(ReflectionProperty $property): string
    {
        if ($property->isPrivate()) {
            return '-' . $property->getName();
        }

        if ($property->isProtected()) {
            return '#' . $property->getName();
        }

        return '+' . $property->getName();
    }

    private function readProperty(ReflectionProperty $property, object $object): mixed
    {
        if (!$property->isInitialized($object)) {
            return '<uninitialized>';
        }

        return $property->getValue($object);
    }
}
