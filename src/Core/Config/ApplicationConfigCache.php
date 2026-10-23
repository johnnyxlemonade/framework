<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Config;

use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionInterface;
use Lemonade\Framework\Core\Config\Exception\ApplicationConfigCacheException;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Throwable;

/**
 * Persists compiled configuration as authenticated data below private application storage.
 *
 * Cache contents are never executed or passed to PHP deserialization. Invalid authenticated data is
 * treated as a miss, while an unsafe filesystem boundary fails explicitly.
 */
final readonly class ApplicationConfigCache
{
    private const int CACHE_VERSION = 2;
    private const int KEY_BYTES = 32;
    private const int CREATE_ATTEMPTS = 10;
    private const int DEFAULT_MAX_FILE_BYTES = 16_777_216;
    private const int DEFAULT_MAX_DATA_DEPTH = 64;
    private const int DEFAULT_MAX_DATA_NODES = 100_000;
    private const string CACHE_DIRECTORY = 'cache/framework/config';
    private const string KEY_DIRECTORY = 'writable/config-cache';
    private const string KEY_FILE = 'application-config-cache.key';
    private const string KEY_INFO = 'lemonade.application-config-cache.v2';

    /**
     * Configures defensive limits for the generated cache without requiring application settings.
     *
     * @throws ApplicationConfigCacheException When a safety limit is not usable.
     */
    public function __construct(
        private int $maxFileBytes = self::DEFAULT_MAX_FILE_BYTES,
        private int $maxDataDepth = self::DEFAULT_MAX_DATA_DEPTH,
        private int $maxDataNodes = self::DEFAULT_MAX_DATA_NODES,
    ) {
        if ($this->maxFileBytes < 1_024) {
            throw new ApplicationConfigCacheException(
                'Application config cache maximum file size must be at least 1024 bytes.',
            );
        }

        if ($this->maxDataDepth < 1 || $this->maxDataDepth > 100) {
            throw new ApplicationConfigCacheException(
                'Application config cache data depth must be between 1 and 100.',
            );
        }

        if ($this->maxDataNodes < 1) {
            throw new ApplicationConfigCacheException(
                'Application config cache data node limit must be positive.',
            );
        }
    }

    /**
     * Returns hydrated definitions only when the authenticated cache and all tracked inputs are fresh.
     *
     * Corrupted, modified, unsupported, or stale data produces a cache miss without invoking a
     * cached class or executing cache contents.
     *
     * @return list<ConfigDefinitionInterface>|null
     * @throws ApplicationConfigCacheException When cache paths, keys, permissions, or files are unsafe.
     */
    public function loadIfFresh(ApplicationContext $context, string $entrypoint): ?array
    {
        if (!$this->enabled($context)) {
            return null;
        }

        $paths = $this->paths($context, $entrypoint);
        $file = $paths['cache_file'];

        if (!file_exists($file) && !is_link($file)) {
            return null;
        }

        $this->prepareStorage($paths);
        $contents = $this->readCacheFile($file);

        if ($contents === null) {
            return null;
        }

        $payload = $this->decodeEnvelope(
            contents: $contents,
            integrityKey: $this->integrityKey($paths),
        );

        if (!$this->isValidPayload($payload, $entrypoint)) {
            return null;
        }

        if (!$this->sourcesMatch($context, $payload['sources'])) {
            return null;
        }

        if (!$this->envMatches($payload['env'])) {
            return null;
        }

        try {
            return $this->hydrateDefinitions($payload['definitions']);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Atomically publishes an authenticated snapshot of definitions and their freshness metadata.
     *
     * @param list<ConfigDefinitionInterface> $definitions
     * @param list<string> $sourceFiles
     * @param list<string> $envKeys
     * @throws ApplicationConfigCacheException When secure persistence cannot be completed.
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

        $paths = $this->paths($context, $entrypoint);
        $this->prepareStorage($paths);

        $payload = [
            'version' => self::CACHE_VERSION,
            'entrypoint' => $entrypoint,
            'sources' => $this->buildSourcesMetadata($context, $sourceFiles),
            'env' => $this->buildEnvMetadata($envKeys),
            'definitions' => $this->serializeDefinitions($definitions),
        ];
        $contents = $this->encodeEnvelope(
            payload: $payload,
            integrityKey: $this->integrityKey($paths),
        );

        if (strlen($contents) > $this->maxFileBytes) {
            throw new ApplicationConfigCacheException(
                'Compiled application config exceeds the configured cache size limit.',
            );
        }

        $this->publishAtomically(
            directory: $paths['cache_directory'],
            targetFile: $paths['cache_file'],
            contents: $contents,
        );
    }

    private function enabled(ApplicationContext $context): bool
    {
        return $context->isProduction();
    }

    /**
     * @return array{
     *     storage_root:string,
     *     public_root:string,
     *     cache_directory:string,
     *     cache_file:string,
     *     key_directory:string,
     *     key_file:string
     * }
     */
    private function paths(ApplicationContext $context, string $entrypoint): array
    {
        $this->assertEntrypoint($entrypoint);

        $storageRoot = $this->assertAbsoluteLocalPath(
            $context->storagePath(),
            'Application storage root',
        );
        $publicRoot = $this->assertAbsoluteLocalPath(
            $context->publicPath(),
            'Public web root',
        );
        $cacheDirectory = $this->assertAbsoluteLocalPath(
            $context->storagePath(self::CACHE_DIRECTORY),
            'Application config cache directory',
        );
        $keyDirectory = $this->assertAbsoluteLocalPath(
            $context->storagePath(self::KEY_DIRECTORY),
            'Application config cache key directory',
        );
        $cacheFile = sprintf(
            '%s%sapplication-%s.cache',
            $cacheDirectory,
            DIRECTORY_SEPARATOR,
            $entrypoint,
        );
        $keyFile = $keyDirectory . DIRECTORY_SEPARATOR . self::KEY_FILE;

        $this->assertContained(
            $cacheDirectory,
            $storageRoot,
            'Application config cache directory escapes application storage.',
        );
        $this->assertContained(
            $cacheFile,
            $storageRoot,
            'Application config cache file escapes application storage.',
        );
        $this->assertContained(
            $keyDirectory,
            $storageRoot,
            'Application config cache key directory escapes application storage.',
        );
        $this->assertContained(
            $keyFile,
            $storageRoot,
            'Application config cache key escapes application storage.',
        );
        $this->assertSeparatedFromPublicRoot($cacheDirectory, $publicRoot);
        $this->assertSeparatedFromPublicRoot($keyDirectory, $publicRoot);
        $this->assertNoSymbolicLinkComponents($cacheDirectory, $storageRoot);
        $this->assertNoSymbolicLinkComponents($keyDirectory, $storageRoot);

        return [
            'storage_root' => $storageRoot,
            'public_root' => $publicRoot,
            'cache_directory' => $cacheDirectory,
            'cache_file' => $cacheFile,
            'key_directory' => $keyDirectory,
            'key_file' => $keyFile,
        ];
    }

    private function assertEntrypoint(string $entrypoint): void
    {
        if (preg_match('/^[a-z][a-z0-9_-]{0,31}$/D', $entrypoint) !== 1) {
            throw new ApplicationConfigCacheException(
                'Application config cache entrypoint must be a safe lowercase identifier.',
            );
        }
    }

    /** @param array{storage_root:string, public_root:string, cache_directory:string, cache_file:string, key_directory:string, key_file:string} $paths */
    private function prepareStorage(array $paths): void
    {
        $this->ensureStorageRoot($paths['storage_root']);
        $this->ensurePrivateDirectory(
            directory: $paths['cache_directory'],
            storageRoot: $paths['storage_root'],
            publicRoot: $paths['public_root'],
        );
        $this->ensurePrivateDirectory(
            directory: $paths['key_directory'],
            storageRoot: $paths['storage_root'],
            publicRoot: $paths['public_root'],
        );
    }

    private function readCacheFile(string $file): ?string
    {
        if (is_link($file) || !is_file($file)) {
            throw new ApplicationConfigCacheException(
                'Application config cache path is not a safe regular file.',
            );
        }

        $this->restrictFilePermissions($file);
        $handle = @fopen($file, 'rb');

        if ($handle === false) {
            throw new ApplicationConfigCacheException('Application config cache file cannot be opened.');
        }

        try {
            if (!@flock($handle, LOCK_SH)) {
                throw new ApplicationConfigCacheException('Application config cache file cannot be locked.');
            }

            $this->assertOpenRegularHandle($handle);
            $this->assertOpenFilePermissions($handle);
            $contents = stream_get_contents($handle, $this->maxFileBytes + 1);

            if (!is_string($contents) || strlen($contents) > $this->maxFileBytes) {
                return null;
            }

            return $contents;
        } finally {
            @flock($handle, LOCK_UN);
            @fclose($handle);
        }
    }

    /** @param array<mixed> $payload */
    private function encodeEnvelope(array $payload, string $integrityKey): string
    {
        $nodes = 0;
        $encodedPayload = $this->encodeValue($payload, 1, $nodes);

        try {
            $body = json_encode(
                [
                    'version' => self::CACHE_VERSION,
                    'payload' => $encodedPayload,
                ],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
                512,
            );
            $mac = hash_hmac('sha256', $body, $integrityKey);

            return json_encode(
                [
                    'body' => base64_encode($body),
                    'mac' => $mac,
                ],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
                8,
            );
        } catch (Throwable $exception) {
            throw new ApplicationConfigCacheException(
                'Compiled application config cannot be encoded safely.',
                previous: $exception,
            );
        }
    }

    /** @return array<mixed>|null */
    private function decodeEnvelope(string $contents, string $integrityKey): ?array
    {
        try {
            $envelope = json_decode($contents, true, 8, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        if (
            !is_array($envelope)
            || !$this->hasExactKeys($envelope, ['body', 'mac'])
            || !is_string($envelope['body'] ?? null)
            || !is_string($envelope['mac'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/D', $envelope['mac']) !== 1
        ) {
            return null;
        }

        $body = base64_decode($envelope['body'], true);
        if ($body === false || strlen($body) > $this->maxFileBytes) {
            return null;
        }

        $expectedMac = hash_hmac('sha256', $body, $integrityKey);
        if (!hash_equals($expectedMac, $envelope['mac'])) {
            return null;
        }

        try {
            $decodedBody = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        if (
            !is_array($decodedBody)
            || !$this->hasExactKeys($decodedBody, ['version', 'payload'])
            || ($decodedBody['version'] ?? null) !== self::CACHE_VERSION
            || !is_array($decodedBody['payload'] ?? null)
        ) {
            return null;
        }

        $nodes = 0;

        try {
            $payload = $this->decodeValue($decodedBody['payload'], 1, $nodes);
        } catch (Throwable) {
            return null;
        }

        return is_array($payload) ? $payload : null;
    }

    /** @return array<string, mixed> */
    private function encodeValue(mixed $value, int $depth, int &$nodes): array
    {
        $this->recordDataNode($depth, $nodes);

        if ($value === null) {
            return ['type' => 'null'];
        }

        if (is_bool($value)) {
            return ['type' => 'bool', 'value' => $value];
        }

        if (is_int($value)) {
            return ['type' => 'int', 'value' => (string) $value];
        }

        if (is_float($value)) {
            return ['type' => 'float', 'value' => base64_encode(pack('E', $value))];
        }

        if (is_string($value)) {
            return ['type' => 'string', 'value' => base64_encode($value)];
        }

        if (!is_array($value)) {
            throw new ApplicationConfigCacheException(sprintf(
                'Compiled application config contains unsupported value of type "%s".',
                get_debug_type($value),
            ));
        }

        $entries = [];

        foreach ($value as $key => $item) {
            $entries[] = [
                'key_type' => is_int($key) ? 'int' : 'string',
                'key' => is_int($key) ? (string) $key : base64_encode($key),
                'value' => $this->encodeValue($item, $depth + 1, $nodes),
            ];
        }

        return ['type' => 'array', 'value' => $entries];
    }

    /**
     * @param array<mixed> $node
     */
    private function decodeValue(array $node, int $depth, int &$nodes): mixed
    {
        $this->recordDataNode($depth, $nodes);
        $type = $node['type'] ?? null;

        if (!is_string($type)) {
            throw new \UnexpectedValueException('Missing compiled config value type.');
        }

        if ($type === 'null') {
            if (!$this->hasExactKeys($node, ['type'])) {
                throw new \UnexpectedValueException('Invalid null config value.');
            }

            return null;
        }

        if (!$this->hasExactKeys($node, ['type', 'value'])) {
            throw new \UnexpectedValueException('Invalid compiled config value structure.');
        }

        $value = $node['value'];

        if ($type === 'bool' && is_bool($value)) {
            return $value;
        }

        if ($type === 'int' && is_string($value) && preg_match('/^-?(?:0|[1-9][0-9]*)$/D', $value) === 1) {
            $integer = (int) $value;

            if ((string) $integer === $value) {
                return $integer;
            }
        }

        if ($type === 'float' && is_string($value)) {
            $binary = base64_decode($value, true);
            $unpacked = is_string($binary) && strlen($binary) === 8
                ? unpack('Evalue', $binary)
                : false;

            if (is_array($unpacked) && is_float($unpacked['value'] ?? null)) {
                return $unpacked['value'];
            }
        }

        if ($type === 'string' && is_string($value)) {
            $decoded = base64_decode($value, true);

            if ($decoded !== false) {
                return $decoded;
            }
        }

        if ($type === 'array' && is_array($value) && array_is_list($value)) {
            return $this->decodeArrayEntries($value, $depth, $nodes);
        }

        throw new \UnexpectedValueException('Invalid compiled config value.');
    }

    /**
     * @param list<mixed> $entries
     * @return array<int|string, mixed>
     */
    private function decodeArrayEntries(array $entries, int $depth, int &$nodes): array
    {
        $resolved = [];

        foreach ($entries as $entry) {
            if (
                !is_array($entry)
                || !$this->hasExactKeys($entry, ['key_type', 'key', 'value'])
                || !is_string($entry['key_type'] ?? null)
                || !is_string($entry['key'] ?? null)
                || !is_array($entry['value'] ?? null)
            ) {
                throw new \UnexpectedValueException('Invalid compiled config array entry.');
            }

            $key = $this->decodeArrayKey($entry['key_type'], $entry['key']);

            if (array_key_exists($key, $resolved)) {
                throw new \UnexpectedValueException('Duplicate compiled config array key.');
            }

            $resolved[$key] = $this->decodeValue($entry['value'], $depth + 1, $nodes);
        }

        return $resolved;
    }

    private function decodeArrayKey(string $type, string $value): int|string
    {
        if ($type === 'string') {
            $decoded = base64_decode($value, true);

            if ($decoded !== false) {
                return $decoded;
            }
        }

        if ($type === 'int' && preg_match('/^-?(?:0|[1-9][0-9]*)$/D', $value) === 1) {
            $integer = (int) $value;

            if ((string) $integer === $value) {
                return $integer;
            }
        }

        throw new \UnexpectedValueException('Invalid compiled config array key.');
    }

    private function recordDataNode(int $depth, int &$nodes): void
    {
        ++$nodes;

        if ($depth > $this->maxDataDepth || $nodes > $this->maxDataNodes) {
            throw new ApplicationConfigCacheException(
                'Compiled application config exceeds its structural safety limits.',
            );
        }
    }

    /**
     * @phpstan-assert-if-true array{
     *     version:int,
     *     entrypoint:string,
     *     sources:list<array{path:string, exists:bool, sha256:string|null}>,
     *     env:array<string, scalar|null>,
     *     definitions:list<array{class:class-string<ConfigDefinitionInterface>, data:array<mixed>}>
     * } $payload
     */
    private function isValidPayload(mixed $payload, string $entrypoint): bool
    {
        if (
            !is_array($payload)
            || !$this->hasExactKeys($payload, ['version', 'entrypoint', 'sources', 'env', 'definitions'])
            || ($payload['version'] ?? null) !== self::CACHE_VERSION
            || ($payload['entrypoint'] ?? null) !== $entrypoint
            || !is_array($payload['sources'] ?? null)
            || !array_is_list($payload['sources'])
            || !is_array($payload['env'] ?? null)
            || !is_array($payload['definitions'] ?? null)
            || !array_is_list($payload['definitions'])
        ) {
            return false;
        }

        foreach ($payload['sources'] as $source) {
            if (!$this->isValidSourceMetadata($source)) {
                return false;
            }
        }

        foreach ($payload['env'] as $key => $value) {
            if (!is_string($key) || !$this->isSafeEnvKey($key) || !$this->isScalarOrNull($value)) {
                return false;
            }
        }

        foreach ($payload['definitions'] as $definition) {
            if (!$this->isValidDefinitionPayload($definition)) {
                return false;
            }
        }

        return true;
    }

    private function isValidSourceMetadata(mixed $source): bool
    {
        if (
            !is_array($source)
            || !$this->hasExactKeys($source, ['path', 'exists', 'sha256'])
            || !is_string($source['path'] ?? null)
            || $source['path'] === ''
            || strlen($source['path']) > 4_096
            || str_contains($source['path'], "\0")
            || !is_bool($source['exists'] ?? null)
        ) {
            return false;
        }

        $hash = $source['sha256'] ?? null;

        return $source['exists']
            ? is_string($hash) && preg_match('/^[a-f0-9]{64}$/D', $hash) === 1
            : $hash === null;
    }

    private function isValidDefinitionPayload(mixed $definition): bool
    {
        if (
            !is_array($definition)
            || !$this->hasExactKeys($definition, ['class', 'data'])
            || !is_string($definition['class'] ?? null)
            || !is_array($definition['data'] ?? null)
        ) {
            return false;
        }

        return strlen($definition['class']) <= 512
            && preg_match('/^(?:[A-Za-z_][A-Za-z0-9_]*\\\\)*[A-Za-z_][A-Za-z0-9_]*$/D', $definition['class']) === 1;
    }

    /** @param list<array{path:string, exists:bool, sha256:string|null}> $sources */
    private function sourcesMatch(ApplicationContext $context, array $sources): bool
    {
        foreach ($sources as $source) {
            $absolutePath = $this->resolveSourcePath($context, $source['path']);
            $exists = is_file($absolutePath);

            if ($exists !== $source['exists']) {
                return false;
            }

            if (!$exists) {
                continue;
            }

            $actualHash = @hash_file('sha256', $absolutePath);
            if (!is_string($actualHash) || !hash_equals((string) $source['sha256'], $actualHash)) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, scalar|null> $expected */
    private function envMatches(array $expected): bool
    {
        foreach ($expected as $key => $value) {
            if ($this->currentEnvValue($key) !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array{class:class-string<ConfigDefinitionInterface>, data:array<mixed>}> $definitions
     * @return list<ConfigDefinitionInterface>
     */
    private function hydrateDefinitions(array $definitions): array
    {
        $hydrator = new CachedConfigDefinitionHydrator();
        $resolved = [];

        foreach ($definitions as $definition) {
            $resolved[] = $hydrator->hydrate($definition['class'], $definition['data']);
        }

        return $resolved;
    }

    /**
     * @param list<string> $sourceFiles
     * @return list<array{path:string, exists:bool, sha256:string|null}>
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
            $hash = null;

            if ($exists) {
                $resolvedHash = @hash_file('sha256', $absolutePath);

                if (!is_string($resolvedHash)) {
                    throw new ApplicationConfigCacheException(sprintf(
                        'Application config source "%s" cannot be hashed.',
                        $absolutePath,
                    ));
                }

                $hash = $resolvedHash;
            }

            $sources[] = [
                'path' => $this->relativeToBase($context, $absolutePath),
                'exists' => $exists,
                'sha256' => $hash,
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
            if (!$this->isSafeEnvKey($key)) {
                throw new ApplicationConfigCacheException('Application config cache received an unsafe environment key.');
            }

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
            $serialized[] = [
                'class' => $definition::class,
                'data' => $definition->toArray(),
            ];
        }

        return $serialized;
    }

    /**
     * @param array<mixed> $value
     * @param list<string> $expected
     */
    private function hasExactKeys(array $value, array $expected): bool
    {
        $keys = array_keys($value);
        sort($keys);
        sort($expected);

        return $keys === $expected;
    }

    private function isSafeEnvKey(string $key): bool
    {
        return $key !== ''
            && strlen($key) <= 255
            && !str_contains($key, "\0")
            && !str_contains($key, '=')
            && preg_match('/[\x00-\x1F\x7F]/', $key) !== 1;
    }

    private function isScalarOrNull(mixed $value): bool
    {
        return is_string($value)
            || is_int($value)
            || is_float($value)
            || is_bool($value)
            || $value === null;
    }

    private function resolveSourcePath(ApplicationContext $context, string $path): string
    {
        if ($this->isAbsolutePath($path)) {
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
        return $this->isScalarOrNull($value) ? $value : null;
    }

    /** @param array{storage_root:string, public_root:string, cache_directory:string, cache_file:string, key_directory:string, key_file:string} $paths */
    private function integrityKey(array $paths): string
    {
        $localKey = $this->loadOrCreateLocalKey($paths);

        return hash_hkdf('sha256', $localKey, self::KEY_BYTES, self::KEY_INFO);
    }

    /** @param array{storage_root:string, public_root:string, cache_directory:string, cache_file:string, key_directory:string, key_file:string} $paths */
    private function loadOrCreateLocalKey(array $paths): string
    {
        $keyFile = $paths['key_file'];

        if (is_link($keyFile) || (file_exists($keyFile) && !is_file($keyFile))) {
            throw new ApplicationConfigCacheException('Application config cache integrity key path is unsafe.');
        }

        set_error_handler(static fn(): bool => true);

        try {
            $handle = file_exists($keyFile)
                ? fopen($keyFile, 'r+b')
                : fopen($keyFile, 'x+b');

            if ($handle === false) {
                $handle = fopen($keyFile, 'r+b');
            }
        } finally {
            restore_error_handler();
        }

        if ($handle === false) {
            throw new ApplicationConfigCacheException('Application config cache integrity key cannot be opened.');
        }

        try {
            if (!@flock($handle, LOCK_EX)) {
                throw new ApplicationConfigCacheException('Application config cache integrity key cannot be locked.');
            }

            $this->assertOpenRegularFile($handle, $keyFile);
            $this->secureOpenFilePermissions($handle, $keyFile);
            $stat = @fstat($handle);

            if (!is_array($stat)) {
                throw new ApplicationConfigCacheException('Application config cache integrity key cannot be inspected.');
            }

            if ($stat['size'] === 0) {
                $this->initializeLocalKey($handle, $keyFile);
            } elseif ($stat['size'] !== self::KEY_BYTES) {
                throw new ApplicationConfigCacheException('Application config cache integrity key has an invalid size.');
            }

            if (!@rewind($handle)) {
                throw new ApplicationConfigCacheException('Application config cache integrity key cannot be read.');
            }

            $key = stream_get_contents($handle, self::KEY_BYTES + 1);

            if (!is_string($key) || strlen($key) !== self::KEY_BYTES) {
                throw new ApplicationConfigCacheException('Application config cache integrity key is incomplete.');
            }

            return $key;
        } finally {
            @flock($handle, LOCK_UN);
            @fclose($handle);
        }
    }

    /** @param resource $handle */
    private function initializeLocalKey($handle, string $path): void
    {
        try {
            $key = random_bytes(self::KEY_BYTES);
        } catch (Throwable $exception) {
            throw new ApplicationConfigCacheException(
                'Application config cache integrity key cannot be generated.',
                previous: $exception,
            );
        }

        if (!@ftruncate($handle, 0) || !@rewind($handle)) {
            throw new ApplicationConfigCacheException('Application config cache integrity key cannot be initialized.');
        }

        $this->writeAll($handle, $key, $path);
        $this->flushAndSync($handle, 'Application config cache integrity key cannot be synchronized.');
    }

    private function publishAtomically(string $directory, string $targetFile, string $contents): void
    {
        if (is_link($targetFile) || (file_exists($targetFile) && !is_file($targetFile))) {
            throw new ApplicationConfigCacheException('Application config cache target path is unsafe.');
        }

        [$temporaryFile, $handle] = $this->createTemporaryFile($directory);

        try {
            $this->writeAll($handle, $contents, $temporaryFile);
            $this->flushAndSync($handle, 'Application config cache temporary file cannot be synchronized.');
            @flock($handle, LOCK_UN);

            if (!@fclose($handle)) {
                $handle = null;
                throw new ApplicationConfigCacheException('Application config cache temporary file cannot be closed.');
            }

            $handle = null;
            $this->assertSecureRegularFile($temporaryFile);

            if (!@rename($temporaryFile, $targetFile)) {
                throw new ApplicationConfigCacheException(
                    'Application config cache cannot be published atomically.',
                );
            }

            $this->assertSecureRegularFile($targetFile);
        } finally {
            if (is_resource($handle)) {
                @flock($handle, LOCK_UN);
                @fclose($handle);
            }

            if (
                str_starts_with(basename($temporaryFile), '.lemonade-config-write-')
                && (file_exists($temporaryFile) || is_link($temporaryFile))
            ) {
                @unlink($temporaryFile);
            }
        }
    }

    /** @return array{0:string, 1:resource} */
    private function createTemporaryFile(string $directory): array
    {
        $previous = null;

        for ($attempt = 0; $attempt < self::CREATE_ATTEMPTS; ++$attempt) {
            try {
                $path = sprintf(
                    '%s%s.lemonade-config-write-%s.tmp',
                    $directory,
                    DIRECTORY_SEPARATOR,
                    bin2hex(random_bytes(16)),
                );
                $handle = @fopen($path, 'x+b');
            } catch (Throwable $exception) {
                $previous = $exception;

                continue;
            }

            if ($handle === false) {
                continue;
            }

            try {
                if (!@flock($handle, LOCK_EX)) {
                    throw new ApplicationConfigCacheException(
                        'Application config cache temporary file cannot be locked.',
                    );
                }

                $this->secureOpenFilePermissions($handle, $path);
                $this->assertOpenRegularFile($handle, $path);

                return [$path, $handle];
            } catch (Throwable $exception) {
                $previous = $exception;
                @flock($handle, LOCK_UN);
                @fclose($handle);
                @unlink($path);
            }
        }

        throw new ApplicationConfigCacheException(
            'Application config cache temporary file cannot be created.',
            previous: $previous,
        );
    }

    /** @param resource $handle */
    private function writeAll($handle, string $contents, string $path): void
    {
        $offset = 0;
        $length = strlen($contents);

        while ($offset < $length) {
            $written = @fwrite($handle, substr($contents, $offset));

            if (!is_int($written) || $written <= 0) {
                throw new ApplicationConfigCacheException(sprintf(
                    'Application config cache data cannot be written to "%s".',
                    $path,
                ));
            }

            $offset += $written;
        }
    }

    /** @param resource $handle */
    private function flushAndSync($handle, string $message): void
    {
        if (!@fflush($handle) || (function_exists('fsync') && !@fsync($handle))) {
            throw new ApplicationConfigCacheException($message);
        }
    }

    private function ensureStorageRoot(string $storageRoot): void
    {
        if (!is_dir($storageRoot) && !@mkdir($storageRoot, 0700, true) && !is_dir($storageRoot)) {
            throw new ApplicationConfigCacheException('Application storage root cannot be created.');
        }

        if (is_link($storageRoot) || !is_dir($storageRoot)) {
            throw new ApplicationConfigCacheException('Application storage root is not a safe directory.');
        }

        clearstatcache(true, $storageRoot);
        $permissions = @fileperms($storageRoot);

        if (!is_int($permissions)) {
            throw new ApplicationConfigCacheException('Application storage root permissions cannot be inspected.');
        }

        if (($permissions & 0022) !== 0) {
            $securedMode = ($permissions & 0777) & ~0022;

            if (!@chmod($storageRoot, $securedMode)) {
                throw new ApplicationConfigCacheException('Application storage root permissions cannot be restricted.');
            }

            clearstatcache(true, $storageRoot);
            $permissions = @fileperms($storageRoot);

            if (!is_int($permissions) || ($permissions & 0022) !== 0) {
                throw new ApplicationConfigCacheException('Application storage root remains writable by other users.');
            }
        }
    }

    private function ensurePrivateDirectory(
        string $directory,
        string $storageRoot,
        string $publicRoot,
    ): void {
        $this->assertContained(
            $directory,
            $storageRoot,
            'Application config cache directory escapes application storage.',
        );
        $this->assertSeparatedFromPublicRoot($directory, $publicRoot);

        $relative = ltrim(substr($directory, strlen(rtrim($storageRoot, '/\\'))), '/\\');
        $cursor = rtrim($storageRoot, '/\\');

        $segments = preg_split('~[\\\\/]~', $relative, -1, PREG_SPLIT_NO_EMPTY);

        if (!is_array($segments)) {
            $segments = [];
        }

        foreach ($segments as $segment) {
            $cursor .= DIRECTORY_SEPARATOR . $segment;

            if (is_link($cursor)) {
                throw new ApplicationConfigCacheException(
                    'Application config cache paths cannot contain symbolic links.',
                );
            }

            if (!is_dir($cursor) && !@mkdir($cursor, 0700) && !is_dir($cursor)) {
                throw new ApplicationConfigCacheException(
                    'Application config cache directory cannot be created securely.',
                );
            }

            clearstatcache(true, $cursor);
            $stat = @lstat($cursor);

            if (!is_array($stat) || ($stat['mode'] & 0170000) !== 0040000 || !@chmod($cursor, 0700)) {
                throw new ApplicationConfigCacheException('Application config cache directory is unsafe.');
            }

            clearstatcache(true, $cursor);
            $permissions = @fileperms($cursor);

            if (!is_int($permissions) || ($permissions & 0777) !== 0700) {
                throw new ApplicationConfigCacheException(
                    'Application config cache directory permissions are unsafe.',
                );
            }
        }

        $this->assertNoSymbolicLinkComponents($directory, $storageRoot);
        $this->assertCanonicalContainment($directory, $storageRoot);
        $this->assertSeparatedFromPublicRoot($directory, $publicRoot);
    }

    private function restrictFilePermissions(string $path): void
    {
        if (!@chmod($path, 0600)) {
            throw new ApplicationConfigCacheException(
                'Application config cache file permissions cannot be restricted.',
            );
        }

        $this->assertSecureRegularFile($path);
    }

    /** @param resource $handle */
    private function secureOpenFilePermissions($handle, string $path): void
    {
        if (!$this->openFileMatchesPath($handle, $path) || !@chmod($path, 0600)) {
            throw new ApplicationConfigCacheException(
                'Application config cache file permissions cannot be secured.',
            );
        }

        $this->assertOpenFilePermissions($handle);
    }

    /** @param resource $handle */
    private function assertOpenFilePermissions($handle): void
    {
        $stat = @fstat($handle);

        if (!is_array($stat) || ($stat['mode'] & 0777) !== 0600) {
            throw new ApplicationConfigCacheException('Application config cache file permissions are unsafe.');
        }
    }

    private function assertSecureRegularFile(string $path): void
    {
        clearstatcache(true, $path);
        $stat = @lstat($path);

        if (
            !is_array($stat)
            || is_link($path)
            || ($stat['mode'] & 0170000) !== 0100000
            || ($stat['mode'] & 0777) !== 0600
        ) {
            throw new ApplicationConfigCacheException(
                'Application config cache published file is unsafe.',
            );
        }
    }

    /** @param resource $handle */
    private function assertOpenRegularFile($handle, string $path): void
    {
        if (!$this->openFileMatchesPath($handle, $path)) {
            throw new ApplicationConfigCacheException(
                'Application config cache file identity cannot be verified.',
            );
        }
    }

    /** @param resource $handle */
    private function assertOpenRegularHandle($handle): void
    {
        $stat = @fstat($handle);

        if (!is_array($stat) || ($stat['mode'] & 0170000) !== 0100000) {
            throw new ApplicationConfigCacheException(
                'Application config cache opened file is not a regular file.',
            );
        }
    }

    /** @param resource $handle */
    private function openFileMatchesPath($handle, string $path): bool
    {
        clearstatcache(true, $path);
        $opened = @fstat($handle);
        $current = @lstat($path);

        return is_array($opened)
            && is_array($current)
            && !is_link($path)
            && ($opened['mode'] & 0170000) === 0100000
            && ($current['mode'] & 0170000) === 0100000
            && $opened['dev'] === $current['dev']
            && $opened['ino'] === $current['ino'];
    }

    private function assertAbsoluteLocalPath(string $path, string $label): string
    {
        $path = rtrim($path, '/\\');
        $portable = str_replace('\\', '/', $path);
        $segments = explode('/', $portable);

        if (
            $path === ''
            || str_contains($path, "\0")
            || str_contains($path, '://')
            || preg_match('/[\x00-\x1F\x7F]/', $path) === 1
            || in_array('.', $segments, true)
            || in_array('..', $segments, true)
            || !$this->isAbsolutePath($path)
        ) {
            throw new ApplicationConfigCacheException(
                $label . ' must be an absolute local path without traversal.',
            );
        }

        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }

    private function assertContained(string $path, string $root, string $message): void
    {
        $path = $this->comparablePath($path);
        $root = $this->comparablePath($root);

        if ($path !== $root && !str_starts_with($path, $root . DIRECTORY_SEPARATOR)) {
            throw new ApplicationConfigCacheException($message);
        }
    }

    private function assertCanonicalContainment(string $path, string $root): void
    {
        $resolvedPath = @realpath($path);
        $resolvedRoot = @realpath($root);

        if (!is_string($resolvedPath) || !is_string($resolvedRoot)) {
            throw new ApplicationConfigCacheException(
                'Application config cache canonical storage boundary cannot be resolved.',
            );
        }

        $this->assertContained(
            $resolvedPath,
            $resolvedRoot,
            'Application config cache path escapes its canonical storage boundary.',
        );
    }

    private function assertSeparatedFromPublicRoot(string $path, string $publicRoot): void
    {
        $path = $this->comparablePath($path);
        $publicRoot = $this->comparablePath($publicRoot);
        $overlaps = $path === $publicRoot
            || str_starts_with($path, $publicRoot . DIRECTORY_SEPARATOR)
            || str_starts_with($publicRoot, $path . DIRECTORY_SEPARATOR);

        if ($overlaps) {
            throw new ApplicationConfigCacheException(
                'Application config cache storage must be separated from the public web root.',
            );
        }

        $resolvedPath = @realpath($path);
        $resolvedPublic = @realpath($publicRoot);

        if (is_string($resolvedPath) && is_string($resolvedPublic)) {
            $resolvedPath = $this->comparablePath($resolvedPath);
            $resolvedPublic = $this->comparablePath($resolvedPublic);

            if (
                $resolvedPath === $resolvedPublic
                || str_starts_with($resolvedPath, $resolvedPublic . DIRECTORY_SEPARATOR)
                || str_starts_with($resolvedPublic, $resolvedPath . DIRECTORY_SEPARATOR)
            ) {
                throw new ApplicationConfigCacheException(
                    'Application config cache canonical path overlaps the public web root.',
                );
            }
        }
    }

    private function assertNoSymbolicLinkComponents(string $path, string $storageRoot): void
    {
        $cursor = $path;

        while (true) {
            if (is_link($cursor)) {
                throw new ApplicationConfigCacheException(
                    'Application config cache paths cannot contain symbolic links.',
                );
            }

            if ($this->comparablePath($cursor) === $this->comparablePath($storageRoot)) {
                return;
            }

            $parent = dirname($cursor);

            if ($parent === $cursor) {
                throw new ApplicationConfigCacheException(
                    'Application config cache path escapes application storage.',
                );
            }

            $cursor = $parent;
        }
    }

    private function comparablePath(string $path): string
    {
        $path = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);

        if ($path === '') {
            $path = DIRECTORY_SEPARATOR;
        }

        return DIRECTORY_SEPARATOR === '\\' ? strtolower($path) : $path;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\\\')
            || preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1;
    }
}
