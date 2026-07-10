<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Config;

use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionInterface;
use Lemonade\Framework\Core\Config\Yaml\YamlDefinitionClassMap;
use Lemonade\Framework\Core\Config\Yaml\YamlDefinitionLoader;
use LogicException;
use RuntimeException;

final class ConfigFileLoader
{
    private const CONFIG_MAP_FILE = 'ConfigMap.php';

    /**
     * @var array<string, YamlDefinitionClassMap>
     */
    private array $yamlClassMapsByDirectory = [];

    public function load(string $file, ?string $rootKey = null): ConfigDefinitionInterface
    {
        if (!is_file($file)) {
            throw new RuntimeException(sprintf('Config file not found: %s', $file));
        }

        $extension = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));
        if ($extension === 'yaml' || $extension === 'yml') {
            return (new YamlDefinitionLoader())->load(
                $file,
                $rootKey,
                $this->yamlClassMapForDirectory(dirname($file)),
            );
        }

        $data = require $file;

        if ($data instanceof ConfigDefinitionInterface) {
            $moduleKey = $data::moduleKey();

            if ($rootKey !== null && $rootKey !== '' && $rootKey !== $moduleKey) {
                throw new LogicException(sprintf(
                    'Config definition "%s" belongs to module "%s", manifest expects "%s".',
                    $data::class,
                    $moduleKey,
                    $rootKey,
                ));
            }

            return $data;
        }

        if ($rootKey !== null && $rootKey !== '') {
            throw new LogicException(sprintf(
                'Root-key manifest mapping is not supported for config definition files: %s',
                $file,
            ));
        }

        throw new RuntimeException(sprintf(
            'Config file "%s" must return an instance of %s. Raw array config is not supported.',
            $file,
            ConfigDefinitionInterface::class,
        ));
    }

    private function yamlClassMapForDirectory(string $directory): YamlDefinitionClassMap
    {
        if (isset($this->yamlClassMapsByDirectory[$directory])) {
            return $this->yamlClassMapsByDirectory[$directory];
        }

        $map = YamlDefinitionClassMap::withDefaults();
        $mapFile = $directory . DIRECTORY_SEPARATOR . self::CONFIG_MAP_FILE;

        if (is_file($mapFile)) {
            /** @var mixed $customMap */
            $customMap = require $mapFile;

            if (!is_array($customMap)) {
                throw new LogicException(sprintf(
                    'Config map file "%s" must return array<string, class-string<ConfigDefinitionInterface>>.',
                    $mapFile,
                ));
            }

            foreach ($customMap as $alias => $definitionClass) {
                if (!is_string($alias) || trim($alias) === '') {
                    throw new LogicException(sprintf(
                        'Config map file "%s" contains invalid alias or definition class.',
                        $mapFile,
                    ));
                }

                $this->assertDefinitionClassString($definitionClass, $mapFile);
                $map->register($alias, $definitionClass);
            }
        }

        $this->yamlClassMapsByDirectory[$directory] = $map;

        return $map;
    }

    /**
     * @phpstan-assert class-string<ConfigDefinitionInterface> $definitionClass
     */
    private function assertDefinitionClassString(mixed $definitionClass, string $mapFile): void
    {
        if (!is_string($definitionClass) || trim($definitionClass) === '') {
            throw new LogicException(sprintf(
                'Config map file "%s" contains invalid alias or definition class.',
                $mapFile,
            ));
        }
    }
}
