<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Config\Yaml;

use Lemonade\Framework\Component\Config\ComponentConfigDefinition;
use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionInterface;
use Lemonade\Framework\Core\Config\ProvidersConfigDefinition;
use LogicException;
use RuntimeException;

final class YamlDefinitionLoader
{
    public function __construct(
        private readonly YamlConfigParser $parser = new YamlConfigParser(),
        private readonly YamlEnvValueResolver $envValueResolver = new YamlEnvValueResolver(),
    ) {}

    public function load(
        string $file,
        ?string $rootKey,
        YamlDefinitionClassMap $classMap,
    ): ConfigDefinitionInterface {
        $document = $this->parser->parseFile($file);

        if (!is_array($document) || array_is_list($document)) {
            throw new RuntimeException(sprintf(
                'YAML config file "%s" must contain a top-level mapping.',
                $file,
            ));
        }

        $module = $document['module'] ?? null;
        if ($module !== null && (!is_string($module) || trim($module) === '')) {
            throw new RuntimeException(sprintf(
                'YAML config file "%s" must define string "module" when present.',
                $file,
            ));
        }

        $payload = $this->resolvePayload($document, $file);
        $payload = $this->envValueResolver->resolve($payload);
        if (!is_array($payload)) {
            throw new RuntimeException(sprintf(
                'YAML config file "%s" produced invalid payload.',
                $file,
            ));
        }

        $alias = pathinfo($file, PATHINFO_FILENAME);
        $definitionClass = $classMap->resolve($alias, is_string($module) ? $module : $rootKey);
        if ($definitionClass === null) {
            throw new RuntimeException(sprintf(
                'No YAML config definition mapping found for file "%s" (alias "%s"%s).',
                $file,
                $alias,
                is_string($module) ? sprintf(', module "%s"', $module) : '',
            ));
        }

        if ($rootKey !== null && $rootKey !== '' && $rootKey !== $definitionClass::moduleKey()) {
            throw new LogicException(sprintf(
                'YAML config definition "%s" belongs to module "%s", manifest expects "%s".',
                $definitionClass,
                $definitionClass::moduleKey(),
                $rootKey,
            ));
        }

        if ($module !== null && trim($module) !== $definitionClass::moduleKey()) {
            throw new RuntimeException(sprintf(
                'YAML config file "%s" declares module "%s" but mapping resolves to "%s".',
                $file,
                $module,
                $definitionClass::moduleKey(),
            ));
        }

        try {
            if (is_subclass_of($definitionClass, AbstractConfigDefinition::class)) {
                if ($definitionClass === ProvidersConfigDefinition::class) {
                    return $this->hydrateProvidersDefinition($payload);
                }

                /** @var class-string<AbstractConfigDefinition&ConfigDefinitionInterface> $definitionClass */
                return $definitionClass::fromArrayData($payload);
            }

            if ($definitionClass === ComponentConfigDefinition::class) {
                return $this->hydrateComponentDefinition($payload);
            }

            throw new RuntimeException(sprintf(
                'YAML config definition "%s" does not support typed payload hydration.',
                $definitionClass,
            ));
        } catch (\InvalidArgumentException $exception) {
            throw new RuntimeException(sprintf(
                'YAML config file "%s" contains data that cannot be converted to "%s": %s',
                $file,
                $definitionClass,
                $exception->getMessage(),
            ), previous: $exception);
        }
    }

    /**
     * @param array<mixed> $document
     * @return array<mixed>
     */
    private function resolvePayload(array $document, string $file): array
    {
        if (array_key_exists('config', $document)) {
            $payload = $document['config'];
            if (!is_array($payload)) {
                throw new RuntimeException(sprintf(
                    'YAML config file "%s" must define "config" as a mapping.',
                    $file,
                ));
            }

            return $payload;
        }

        unset($document['module']);

        return $document;
    }

    /**
     * @param array<mixed> $payload
     */
    private function hydrateComponentDefinition(array $payload): ComponentConfigDefinition
    {
        $definition = ComponentConfigDefinition::create();

        foreach ($payload as $name => $componentClass) {
            if (!is_string($name) || trim($name) === '' || !is_string($componentClass) || trim($componentClass) === '') {
                throw new \InvalidArgumentException(
                    'Component config payload must map non-empty component names to non-empty class strings.',
                );
            }

            if (!class_exists($componentClass)) {
                throw new \InvalidArgumentException(sprintf(
                    'Component config payload references unknown component class "%s".',
                    $componentClass,
                ));
            }

            $definition->component($name, $componentClass);
        }

        return $definition;
    }

    /**
     * @param array<mixed> $payload
     */
    private function hydrateProvidersDefinition(array $payload): ProvidersConfigDefinition
    {
        $providers = $payload['providers'] ?? $payload;
        if (!is_array($providers)) {
            throw new \InvalidArgumentException(
                'Providers config payload must be a list of provider class names or contain a "providers" list.',
            );
        }

        $normalized = [];

        foreach ($providers as $providerClass) {
            if (!is_string($providerClass) || trim($providerClass) === '') {
                throw new \InvalidArgumentException(
                    'Providers config payload must contain only non-empty provider class strings.',
                );
            }

            $normalized[] = $providerClass;
        }

        return ProvidersConfigDefinition::create()->providers($normalized);
    }
}
