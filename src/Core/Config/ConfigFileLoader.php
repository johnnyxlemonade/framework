<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Config;

use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionInterface;
use LogicException;
use RuntimeException;

final class ConfigFileLoader
{
    public function load(string $file, ?string $rootKey = null): ConfigDefinitionInterface
    {
        if (!is_file($file)) {
            throw new RuntimeException(sprintf('Config file not found: %s', $file));
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
}
