<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Config;

use Lemonade\Framework\Component\Config\ComponentConfigDefinition;
use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionInterface;
use RuntimeException;

final class CachedConfigDefinitionHydrator
{
    /**
     * @param class-string<ConfigDefinitionInterface> $definitionClass
     * @param array<mixed> $data
     */
    public function hydrate(string $definitionClass, array $data): ConfigDefinitionInterface
    {
        if (is_subclass_of($definitionClass, AbstractConfigDefinition::class)) {
            /** @var class-string<AbstractConfigDefinition&ConfigDefinitionInterface> $definitionClass */
            return $definitionClass::fromArrayData($data);
        }

        if ($definitionClass === ComponentConfigDefinition::class) {
            return ComponentConfigDefinition::create()->components(
                $this->normalizeComponentMap($data),
            );
        }

        throw new RuntimeException(sprintf(
            'Cached config definition "%s" does not support hydration from cached array payload.',
            $definitionClass,
        ));
    }

    /**
     * @param array<mixed> $data
     * @return array<string, class-string>
     */
    private function normalizeComponentMap(array $data): array
    {
        $components = [];

        foreach ($data as $name => $className) {
            if (!is_string($name) || trim($name) === '' || !is_string($className) || trim($className) === '') {
                throw new RuntimeException('Invalid cached component config payload.');
            }

            if (!class_exists($className)) {
                throw new RuntimeException(sprintf(
                    'Invalid cached component config class "%s".',
                    $className,
                ));
            }

            /** @var class-string $className */
            $components[$name] = $className;
        }

        return $components;
    }
}
