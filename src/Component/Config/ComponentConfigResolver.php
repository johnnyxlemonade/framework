<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Config;

use LogicException;

/**
 * Merges component definitions into a validated immutable configuration.
 *
 * Definitions are processed in order, so a later registration replaces an earlier one with the same name.
 */
final class ComponentConfigResolver
{
    /**
     * Resolves ordered definitions and rejects invalid component names or class references.
     *
     * @throws LogicException When a definition contains an invalid name or an unavailable class.
     */
    public function resolve(ComponentConfigDefinition ...$definitions): ComponentConfig
    {
        $components = [];

        foreach ($definitions as $definition) {
            foreach ($definition->toArray() as $name => $componentClass) {
                if (!is_string($name) || trim($name) === '') {
                    throw new LogicException(sprintf(
                        'Config key [components] must use non-empty string keys, %s given.',
                        get_debug_type($name),
                    ));
                }

                if (!is_string($componentClass) || trim($componentClass) === '') {
                    throw new LogicException(sprintf(
                        'Component [%s] must be a non-empty class-string, %s given.',
                        $name,
                        get_debug_type($componentClass),
                    ));
                }

                if (!class_exists($componentClass)) {
                    throw new LogicException(sprintf(
                        'Component [%s] references non-existing class [%s].',
                        $name,
                        $componentClass,
                    ));
                }

                /** @var class-string $componentClass */
                $components[trim($name)] = $componentClass;
            }
        }

        return new ComponentConfig($components);
    }
}
