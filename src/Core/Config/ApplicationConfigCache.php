<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Config;

use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionInterface;
use Lemonade\Framework\Core\Context\ApplicationContext;

final class ApplicationConfigCache
{
    private const CACHE_VERSION = 1;

    /**
     * @return list<ConfigDefinitionInterface>|null
     */
    public function loadIfFresh(ApplicationContext $context, string $entrypoint): ?array
    {
        if (!$this->enabled($context)) {
            return null;
        }

        $file = $this->cacheFile($context, $entrypoint);

        if (!is_file($file)) {
            return null;
        }

        /** @var mixed $payload */
        $payload = require $file;

        if (!$this->isValidPayload($payload, $entrypoint)) {
            return null;
        }

        /** @var array{
         *     version:int,
         *     entrypoint:string,
         *     sources:list<array{path:string, exists:bool, sha1:string|null}>,
         *     env:array<string, scalar|null>,
         *     definitions:list<array{class:class-string<ConfigDefinitionInterface>, data:array<mixed>}>
         * } $payload
         */

        if (!$this->sourcesMatch($context, $payload['sources'])) {
            return null;
        }

        if (!$this->envMatches($payload['env'])) {
            return null;
        }

        return $this->hydrateDefinitions($payload['definitions']);
    }

    /**
     * @param list<ConfigDefinitionInterface> $definitions
     * @param list<string> $sourceFiles
     * @param list<string> $envKeys
     */
    public function write(
        ApplicationContext $context,
        string $entrypoint,
        array $definitions,
        array $sourceFiles,
        array $envKeys,
    ): void {
        if (!$this->enabled($context)) {
            return;
        }

        $payload = [
            'version' => self::CACHE_VERSION,
            'entrypoint' => $entrypoint,
            'sources' => $this->buildSourcesMetadata($context, $sourceFiles),
            'env' => $this->buildEnvMetadata($envKeys),
            'definitions' => $this->serializeDefinitions($definitions),
        ];

        $targetFile = $this->cacheFile($context, $entrypoint);
        $directory = dirname($targetFile);

        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return;
        }

        $php = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($payload, true) . ";\n";
        $temporaryFile = $targetFile . '.tmp';

        if (@file_put_contents($temporaryFile, $php) === false) {
            return;
        }

        if (is_file($targetFile) && !@unlink($targetFile)) {
            @unlink($temporaryFile);

            return;
        }

        if (!@rename($temporaryFile, $targetFile)) {
            @unlink($temporaryFile);
        }
    }

    private function enabled(ApplicationContext $context): bool
    {
        return $context->isProduction();
    }

    private function cacheFile(ApplicationContext $context, string $entrypoint): string
    {
        return $context->resolveCachePath(
            'framework/config/application-' . $entrypoint . '.php',
        );
    }

    /**
     * @phpstan-assert-if-true array{
     *     version:int,
     *     entrypoint:string,
     *     sources:list<array{path:string, exists:bool, sha1:string|null}>,
     *     env:array<string, scalar|null>,
     *     definitions:list<array{class:class-string<ConfigDefinitionInterface>, data:array<mixed>}>
     * } $payload
     */
    private function isValidPayload(mixed $payload, string $entrypoint): bool
    {
        if (!is_array($payload)) {
            return false;
        }

        if (($payload['version'] ?? null) !== self::CACHE_VERSION) {
            return false;
        }

        if (($payload['entrypoint'] ?? null) !== $entrypoint) {
            return false;
        }

        if (!isset($payload['sources'], $payload['env'], $payload['definitions'])) {
            return false;
        }

        return is_array($payload['sources'])
            && is_array($payload['env'])
            && is_array($payload['definitions']);
    }

    /**
     * @param array<mixed> $sources
     */
    private function sourcesMatch(ApplicationContext $context, array $sources): bool
    {
        foreach ($sources as $source) {
            if (!is_array($source)) {
                return false;
            }

            $relativePath = $source['path'] ?? null;
            $expectedExists = $source['exists'] ?? null;
            $expectedHash = $source['sha1'] ?? null;

            if (!is_string($relativePath) || !is_bool($expectedExists)) {
                return false;
            }

            $absolutePath = $context->path($relativePath);
            $exists = is_file($absolutePath);

            if ($exists !== $expectedExists) {
                return false;
            }

            if (!$exists) {
                continue;
            }

            if (!is_string($expectedHash) || $expectedHash === '') {
                return false;
            }

            $actualHash = @sha1_file($absolutePath);
            if (!is_string($actualHash) || $actualHash !== $expectedHash) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, scalar|null> $expected
     */
    private function envMatches(array $expected): bool
    {
        foreach ($expected as $key => $value) {
            if (!is_string($key)) {
                return false;
            }

            if ($this->currentEnvValue($key) !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<ConfigDefinitionInterface>
     */
    /**
     * @param list<array{class:class-string<ConfigDefinitionInterface>, data:array<mixed>}> $definitions
     * @return list<ConfigDefinitionInterface>
     */
    private function hydrateDefinitions(array $definitions): array
    {
        $hydrator = new CachedConfigDefinitionHydrator();
        $resolved = [];

        foreach ($definitions as $definition) {
            $definitionClass = $definition['class'];
            $data = $definition['data'];
            $resolved[] = $hydrator->hydrate($definitionClass, $data);
        }

        return $resolved;
    }

    /**
     * @param list<string> $sourceFiles
     * @return list<array{path:string, exists:bool, sha1:string|null}>
     */
    private function buildSourcesMetadata(ApplicationContext $context, array $sourceFiles): array
    {
        $uniqueFiles = array_values(array_unique(array_map(
            static fn(string $path): string => str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path),
            $sourceFiles,
        )));

        $sources = [];

        foreach ($uniqueFiles as $path) {
            $absolutePath = $this->resolveSourcePath($context, $path);
            $exists = is_file($absolutePath);
            $hash = $exists ? @sha1_file($absolutePath) : null;

            $sources[] = [
                'path' => $this->relativeToBase($context, $absolutePath),
                'exists' => $exists,
                'sha1' => is_string($hash) ? $hash : null,
            ];
        }

        return $sources;
    }

    /**
     * @param list<string> $envKeys
     * @return array<string, scalar|null>
     */
    private function buildEnvMetadata(array $envKeys): array
    {
        $keys = array_values(array_unique($envKeys));
        sort($keys);

        $metadata = [];

        foreach ($keys as $key) {
            $metadata[$key] = $this->currentEnvValue($key);
        }

        return $metadata;
    }

    /**
     * @param list<ConfigDefinitionInterface> $definitions
     * @return list<array{class:class-string<ConfigDefinitionInterface>, data:array<mixed>}>
     */
    private function serializeDefinitions(array $definitions): array
    {
        $serialized = [];

        foreach ($definitions as $definition) {
            $className = $definition::class;

            $serialized[] = [
                'class' => $className,
                'data' => $definition->toArray(),
            ];
        }

        return $serialized;
    }

    private function resolveSourcePath(ApplicationContext $context, string $path): string
    {
        if (
            str_starts_with($path, '\\\\')
            || str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1
        ) {
            return $path;
        }

        return $context->path($path);
    }

    private function relativeToBase(ApplicationContext $context, string $absolutePath): string
    {
        $basePath = rtrim($context->basePath(), '/\\');
        $normalizedAbsolute = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $absolutePath);
        $normalizedBase = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $basePath);

        if (
            str_starts_with(strtolower($normalizedAbsolute), strtolower($normalizedBase . DIRECTORY_SEPARATOR))
            || strtolower($normalizedAbsolute) === strtolower($normalizedBase)
        ) {
            $relative = substr($normalizedAbsolute, strlen($normalizedBase));

            return ltrim(str_replace('\\', '/', $relative), '/');
        }

        return str_replace('\\', '/', $normalizedAbsolute);
    }

    private function currentEnvValue(string $key): string|int|float|bool|null
    {
        if (array_key_exists($key, $_ENV)) {
            return $this->normalizeEnvValue($_ENV[$key]);
        }

        if (array_key_exists($key, $_SERVER)) {
            return $this->normalizeEnvValue($_SERVER[$key]);
        }

        $value = getenv($key);

        return $value === false
            ? null
            : $this->normalizeEnvValue($value);
    }

    private function normalizeEnvValue(mixed $value): string|int|float|bool|null
    {
        if (
            is_string($value)
            || is_int($value)
            || is_float($value)
            || is_bool($value)
            || $value === null
        ) {
            return $value;
        }

        return null;
    }
}
