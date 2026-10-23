<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Config;

use Lemonade\Framework\Core\Config\Yaml\YamlConfigParser;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Framework;
use LogicException;

/**
 * Loads application configuration for HTTP and CLI entrypoints.
 */
final class ConfigLoader
{
    public const ENTRYPOINT_HTTP = 'http';
    public const ENTRYPOINT_CLI = 'cli';
    private const CONFIG_MANIFEST_CANDIDATES = ['Config.yaml', 'Config.yml'];
    private const CONFIG_MANIFEST_SECTIONS = ['shared', 'http', 'cli'];

    /**
     * Loads all configuration definitions required by one application entrypoint.
     */
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
     * Returns the explicitly declared config files for one entrypoint.
     *
     * A missing or empty manifest declares no application config files. Present
     * manifests reject unknown keys and invalid section or file-name values.
     *
     * @return list<string> Shared files followed by entrypoint-specific files.
     */
    public function resolveConfigFileSpecs(
        ApplicationContext $context,
        string $entrypoint,
    ): array {
        $this->assertSupportedEntrypoint($entrypoint);
        $manifestPath = $this->resolveManifestPath($context);

        if ($manifestPath === null) {
            return [];
        }

        return $this->resolveConfigFileSpecsFromManifest($manifestPath, $entrypoint);
    }

    /**
     * @return list<string>
     */
    private function resolveConfigFileSpecsFromManifest(string $manifestPath, string $entrypoint): array
    {
        $this->assertSupportedEntrypoint($entrypoint);
        $manifest = $this->loadManifest($manifestPath);
        $sections = [];

        foreach (self::CONFIG_MANIFEST_SECTIONS as $section) {
            $sections[$section] = $this->manifestSection(
                $manifest,
                $section,
                $manifestPath,
            );
        }

        return [
            ...$sections['shared'],
            ...$sections[$entrypoint],
        ];
    }

    /**
     * @param array<mixed> $manifest
     * @return list<string>
     */
    private function manifestSection(
        array $manifest,
        string $section,
        string $manifestPath,
    ): array {
        if (!array_key_exists($section, $manifest)) {
            return [];
        }

        $files = $manifest[$section];

        if (!is_array($files) || !array_is_list($files)) {
            throw new LogicException(sprintf(
                'Config manifest "%s" section "%s" must be a list of file names.',
                basename($manifestPath),
                $section,
            ));
        }

        return $this->normalizeFilesList($files);
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

    /**
     * Finds the supported configuration manifest in the application configuration directory.
     */
    private function resolveManifestPath(ApplicationContext $context): ?string
    {
        foreach ($this->resolveManifestCandidates($context) as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /** @return list<string> */
    private function resolveManifestCandidates(ApplicationContext $context): array
    {
        return array_map(
            static fn(string $candidate): string => $context->configPath($candidate),
            self::CONFIG_MANIFEST_CANDIDATES,
        );
    }

    /**
     * @return array<mixed>
     */
    private function loadManifest(string $manifestPath): array
    {
        /** @var mixed $manifest */
        $manifest = (new YamlConfigParser())->parseFile($manifestPath);

        if ($manifest === null) {
            return [];
        }

        if (!is_array($manifest)) {
            throw new LogicException(sprintf(
                'Config manifest "%s" must contain a YAML mapping.',
                basename($manifestPath),
            ));
        }

        foreach (array_keys($manifest) as $key) {
            if (!is_string($key) || !in_array($key, self::CONFIG_MANIFEST_SECTIONS, true)) {
                throw new LogicException(sprintf(
                    'Config manifest "%s" contains unknown key "%s".',
                    basename($manifestPath),
                    (string) $key,
                ));
            }
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
        $this->assertSupportedEntrypoint($entrypoint);
        $manifestCandidates = $this->resolveManifestCandidates($context);
        $manifestPath = $this->resolveManifestPath($context);
        $sourceFiles = $manifestCandidates;

        if ($manifestPath === null) {
            return [[], $sourceFiles, []];
        }

        $specs = $this->resolveConfigFileSpecsFromManifest($manifestPath, $entrypoint);
        $fileLoader = new ConfigFileLoader();
        $definitions = [];
        $envKeys = [];

        foreach ($specs as $file) {
            $candidates = $this->resolveConfigFileCandidates($context, $file);

            foreach ($candidates as $candidate) {
                $sourceFiles[] = $candidate;
            }

            $path = $this->resolveConfigFilePathFromCandidates($candidates);
            if ($path === null) {
                throw new LogicException(sprintf(
                    'Config file "%s" declared by manifest "%s" was not found. Expected one of: %s.',
                    $file,
                    basename($manifestPath),
                    implode(', ', $candidates),
                ));
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

    private function assertSupportedEntrypoint(string $entrypoint): void
    {
        if ($entrypoint !== self::ENTRYPOINT_HTTP && $entrypoint !== self::ENTRYPOINT_CLI) {
            throw new LogicException(sprintf('Unsupported config entrypoint "%s".', $entrypoint));
        }
    }
}
