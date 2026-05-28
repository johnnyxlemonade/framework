<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Config;

use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Framework;
use LogicException;

final class ConfigLoader
{
    private const CONFIG_MANIFEST = 'Config.php';

    /**
     * @param list<string> $conventionalConfigFiles
     */
    public function load(
        Framework $framework,
        ApplicationContext $context,
        array $conventionalConfigFiles,
    ): void {
        foreach ($this->resolveConfigFileNames($context, $conventionalConfigFiles) as $file) {
            $path = $context->configPath($file);

            if (!is_file($path)) {
                continue;
            }

            $framework->configFromFile($path);
        }
    }

    /**
     * @param list<string> $conventionalConfigFiles
     * @return list<string>
     */
    public function resolveConfigFileNames(
        ApplicationContext $context,
        array $conventionalConfigFiles,
    ): array {
        $manifestPath = $context->configPath(self::CONFIG_MANIFEST);

        if (!is_file($manifestPath)) {
            return $conventionalConfigFiles;
        }

        /** @var mixed $manifest */
        $manifest = require $manifestPath;

        if (!is_array($manifest)) {
            throw new LogicException(sprintf(
                'Config manifest "%s" must return an array.',
                self::CONFIG_MANIFEST,
            ));
        }

        /** @var mixed $files */
        $files = $manifest['files'] ?? null;

        if (!is_array($files)) {
            throw new LogicException(sprintf(
                'Config manifest "%s" must contain array key "files".',
                self::CONFIG_MANIFEST,
            ));
        }

        $normalized = [];

        foreach ($files as $file) {
            if (!is_string($file)) {
                throw new LogicException(sprintf(
                    'Config manifest "%s" contains invalid file name.',
                    self::CONFIG_MANIFEST,
                ));
            }

            $file = trim($file);

            if ($file === '') {
                throw new LogicException(sprintf(
                    'Config manifest "%s" contains invalid file name.',
                    self::CONFIG_MANIFEST,
                ));
            }

            $normalized[] = $file;
        }

        return $normalized;
    }
}
