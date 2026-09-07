<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Config;

use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionInterface;
use RuntimeException;

final class FrameworkDefaultsLoader
{
    /**
     * @return list<ConfigDefinitionInterface>
     */
    public function load(): array
    {
        $configDirectory = dirname(__DIR__, 2) . '/Config';
        $manifestPath = $configDirectory . '/Config.php';

        if (!is_file($manifestPath)) {
            throw new RuntimeException(sprintf('Framework config manifest not found: %s', $manifestPath));
        }

        $manifest = require $manifestPath;
        if (!is_array($manifest)) {
            throw new RuntimeException(sprintf('Framework config manifest "%s" must return array.', $manifestPath));
        }

        $shared = $manifest['shared'] ?? null;
        $http = $manifest['http'] ?? null;
        $cli = $manifest['cli'] ?? null;
        if (!is_array($shared) || !is_array($http) || !is_array($cli)) {
            throw new RuntimeException(sprintf(
                'Framework config manifest "%s" must contain array keys "shared", "http", and "cli".',
                $manifestPath,
            ));
        }

        $definitions = [];

        foreach ($this->normalizeManifestSection($shared, $manifestPath) as $fileName) {
            $defaultsFile = $configDirectory . '/' . $fileName;
            if (!is_file($defaultsFile)) {
                continue;
            }

            $definitions[] = (new ConfigFileLoader())->load($defaultsFile);
        }

        return $definitions;
    }

    /**
     * @param array<mixed> $section
     * @return list<string>
     */
    private function normalizeManifestSection(array $section, string $manifestPath): array
    {
        $normalized = [];

        foreach ($section as $fileName) {
            if (!is_string($fileName) || trim($fileName) === '') {
                throw new RuntimeException(sprintf(
                    'Framework config manifest "%s" contains invalid file name.',
                    $manifestPath,
                ));
            }

            $normalized[] = trim($fileName);
        }

        return $normalized;
    }
}
