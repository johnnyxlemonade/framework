<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Config;

use Lemonade\Framework\Core\Config\Yaml\YamlConfigParser;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Framework;
use LogicException;

final class ConfigLoader
{
    public const ENTRYPOINT_HTTP = 'http';
    public const ENTRYPOINT_CLI = 'cli';
    private const CONFIG_MANIFEST_CANDIDATES = ['Config.yaml', 'Config.yml'];

    public function loadApplication(
        Framework $framework,
        ApplicationContext $context,
        string $entrypoint,
    ): void {
        /** @var list<string> $specs */
        $specs = $this->resolveConfigFileSpecs($context, $entrypoint);

        foreach ($specs as $file) {
            $path = $this->resolveConfigFilePath($context, $file);
            if ($path === null) {
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

        $manifestPath = $this->resolveManifestPath($context);
        $manifest = $this->loadManifest($manifestPath);

        $shared = $manifest['shared'] ?? null;
        $http = $manifest['http'] ?? null;
        $cli = $manifest['cli'] ?? null;
        if (!is_array($shared) || !is_array($http) || !is_array($cli)) {
            throw new LogicException(sprintf(
                'Config manifest "%s" must contain array keys "shared", "http", and "cli".',
                basename($manifestPath),
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
                    'Config manifest contains invalid file name.',
                ));
            }

            $normalized[] = trim($value);
        }

        return $normalized;
    }

    private function resolveManifestPath(ApplicationContext $context): string
    {
        foreach (self::CONFIG_MANIFEST_CANDIDATES as $candidate) {
            $path = $context->configPath($candidate);
            if (is_file($path)) {
                return $path;
            }
        }

        throw new LogicException(sprintf(
            'Config manifest not found. Expected one of: %s.',
            implode(', ', self::CONFIG_MANIFEST_CANDIDATES),
        ));
    }

    /**
     * @return array<mixed>
     */
    private function loadManifest(string $manifestPath): array
    {
        /** @var mixed $manifest */
        $manifest = (new YamlConfigParser())->parseFile($manifestPath);

        if (!is_array($manifest)) {
            throw new LogicException(sprintf(
                'Config manifest "%s" must contain a YAML mapping.',
                basename($manifestPath),
            ));
        }

        return $manifest;
    }

    private function resolveConfigFilePath(ApplicationContext $context, string $spec): ?string
    {
        $trimmed = trim($spec);
        $exact = $context->configPath($trimmed);
        if (is_file($exact)) {
            return $exact;
        }

        if (pathinfo($trimmed, PATHINFO_EXTENSION) !== '') {
            return null;
        }

        foreach (['yaml', 'yml'] as $extension) {
            $candidate = $context->configPath($trimmed . '.' . $extension);
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
