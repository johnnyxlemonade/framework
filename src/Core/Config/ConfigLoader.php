<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Config;

use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Framework;
use LogicException;

final class ConfigLoader
{
    public const ENTRYPOINT_HTTP = 'http';
    public const ENTRYPOINT_CLI = 'cli';
    private const CONFIG_MANIFEST = 'Config.php';

    public function loadApplication(
        Framework $framework,
        ApplicationContext $context,
        string $entrypoint,
    ): void {
        /** @var list<string> $specs */
        $specs = $this->resolveConfigFileSpecs($context, $entrypoint);

        foreach ($specs as $file) {
            $path = $context->configPath($file);

            if (!is_file($path)) {
                continue;
            }

            $framework->configFromFile($path);
        }
    }

    /**
     * @return list<string>
     */
    public function resolveConfigFileSpecs(
        ApplicationContext $context,
        string $entrypoint,
    ): array {
        if ($entrypoint !== self::ENTRYPOINT_HTTP && $entrypoint !== self::ENTRYPOINT_CLI) {
            throw new LogicException(sprintf('Unsupported config entrypoint "%s".', $entrypoint));
        }

        $manifestPath = $context->configPath(self::CONFIG_MANIFEST);
        if (!is_file($manifestPath)) {
            throw new LogicException(sprintf('Config manifest "%s" not found.', self::CONFIG_MANIFEST));
        }

        /** @var mixed $manifest */
        $manifest = require $manifestPath;

        if (!is_array($manifest)) {
            throw new LogicException(sprintf(
                'Config manifest "%s" must return an array.',
                self::CONFIG_MANIFEST,
            ));
        }

        $shared = $manifest['shared'] ?? null;
        $http = $manifest['http'] ?? null;
        $cli = $manifest['cli'] ?? null;
        if (!is_array($shared) || !is_array($http) || !is_array($cli)) {
            throw new LogicException(sprintf(
                'Config manifest "%s" must contain array keys "shared", "http", and "cli".',
                self::CONFIG_MANIFEST,
            ));
        }

        $sections = $entrypoint === self::ENTRYPOINT_HTTP
            ? [$shared, $http]
            : [$shared, $cli];

        /** @var list<string> $resolved */
        $resolved = [];
        foreach ($sections as $section) {
            foreach ($this->normalizeFilesList($section) as $file) {
                $resolved[] = $file;
            }
        }

        return $resolved;
    }

    /**
     * @param array<mixed> $files
     * @return list<string>
     */
    private function normalizeFilesList(array $files): array
    {
        /** @var list<string> $normalized */
        $normalized = [];

        foreach ($files as $value) {
            if (!is_string($value) || trim($value) === '') {
                throw new LogicException(sprintf(
                    'Config manifest "%s" contains invalid file name.',
                    self::CONFIG_MANIFEST,
                ));
            }

            $normalized[] = trim($value);
        }

        return $normalized;
    }
}
