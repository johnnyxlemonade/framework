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
        /** @var list<array{file: string, root_key: ?string}> $specs */
        $specs = $this->resolveConfigFileSpecs($context, $entrypoint);

        foreach ($specs as $spec) {
            $path = $context->configPath($spec['file']);

            if (!is_file($path)) {
                continue;
            }

            $framework->configFromFile($path, $spec['root_key']);
        }
    }

    /**
     * @return list<array{file: string, root_key: ?string}>
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

        /** @var list<array{file: string, root_key: ?string}> $resolved */
        $resolved = [];
        foreach ($sections as $section) {
            foreach ($this->normalizeFilesMapping($section) as $spec) {
                $resolved[] = $spec;
            }
        }

        return $resolved;
    }

    /**
     * @param array<mixed, mixed> $files
     * @return list<array{file: string, root_key: ?string}>
     */
    private function normalizeFilesMapping(array $files): array
    {
        /** @var list<array{file: string, root_key: ?string}> $normalized */
        $normalized = [];

        foreach ($files as $key => $value) {
            if (!is_string($key) || trim($key) === '') {
                throw new LogicException(sprintf(
                    'Config manifest "%s" contains invalid file name.',
                    self::CONFIG_MANIFEST,
                ));
            }

            if (!is_string($value) && $value !== null) {
                throw new LogicException(sprintf(
                    'Config manifest "%s" contains invalid root key mapping.',
                    self::CONFIG_MANIFEST,
                ));
            }

            if (is_string($value) && trim($value) === '') {
                throw new LogicException(sprintf(
                    'Config manifest "%s" contains invalid root key mapping.',
                    self::CONFIG_MANIFEST,
                ));
            }

            $normalized[] = [
                'file' => trim($key),
                'root_key' => is_string($value) ? trim($value) : null,
            ];
        }

        return $normalized;
    }
}
