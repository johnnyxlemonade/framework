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
        if (!$context->isProduction()) {
            $framework->config(...$this->loadDefinitions($context, $entrypoint));

            return;
        }

        $cache = new ApplicationConfigCache();
        $cachedDefinitions = $cache->loadIfFresh($context, $entrypoint);

        if ($cachedDefinitions !== null) {
            $framework->config(...$cachedDefinitions);

            return;
        }

        [$definitions, $sourceFiles, $envKeys] = $this->loadDefinitionsWithMetadata($context, $entrypoint);

        $framework->config(...$definitions);
        $cache->write($context, $entrypoint, $definitions, $sourceFiles, $envKeys);
    }

    /**
     * @return list<string>
     */
    public function resolveConfigFileSpecs(
        ApplicationContext $context,
        string $entrypoint,
    ): array {
        $manifestPath = $this->resolveManifestPath($context);

        return $this->resolveConfigFileSpecsFromManifest($manifestPath, $entrypoint);
    }

    /**
     * @return list<string>
     */
    private function resolveConfigFileSpecsFromManifest(string $manifestPath, string $entrypoint): array
    {
        if ($entrypoint !== self::ENTRYPOINT_HTTP && $entrypoint !== self::ENTRYPOINT_CLI) {
            throw new LogicException(sprintf('Unsupported config entrypoint "%s".', $entrypoint));
        }

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

    /**
     * @return list<Definition\ConfigDefinitionInterface>
     */
    private function loadDefinitions(ApplicationContext $context, string $entrypoint): array
    {
        [$definitions] = $this->loadDefinitionsWithMetadata($context, $entrypoint);

        return $definitions;
    }

    /**
     * @return array{
     *     0:list<Definition\ConfigDefinitionInterface>,
     *     1:list<string>,
     *     2:list<string>
     * }
     */
    private function loadDefinitionsWithMetadata(ApplicationContext $context, string $entrypoint): array
    {
        $manifestPath = $this->resolveManifestPath($context);
        $specs = $this->resolveConfigFileSpecsFromManifest($manifestPath, $entrypoint);
        $fileLoader = new ConfigFileLoader();
        $definitions = [];
        $sourceFiles = [$manifestPath];
        $envKeys = [];

        foreach ($specs as $file) {
            $candidates = $this->resolveConfigFileCandidates($context, $file);

            foreach ($candidates as $candidate) {
                $sourceFiles[] = $candidate;
            }

            $path = $this->resolveConfigFilePathFromCandidates($candidates);
            if ($path === null) {
                continue;
            }

            $loaded = $fileLoader->loadWithMetadata($path);
            $definitions[] = $loaded->definition();
            array_push($envKeys, ...$loaded->envKeys());
            array_push($sourceFiles, ...$loaded->sourceFiles());
        }

        return [$definitions, $sourceFiles, $envKeys];
    }

    /**
     * @return list<string>
     */
    private function resolveConfigFileCandidates(ApplicationContext $context, string $spec): array
    {
        $trimmed = trim($spec);
        $exact = $context->configPath($trimmed);

        if (pathinfo($trimmed, PATHINFO_EXTENSION) !== '') {
            return [$exact];
        }

        return [
            $exact,
            $context->configPath($trimmed . '.yaml'),
            $context->configPath($trimmed . '.yml'),
        ];
    }

    /**
     * @param list<string> $candidates
     */
    private function resolveConfigFilePathFromCandidates(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
