<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cache\Store;

use DateTimeImmutable;
use Lemonade\Framework\Cache\CacheKeyValidator;
use Lemonade\Framework\Cache\Exception\CacheStorageException;
use Lemonade\Framework\Cache\Exception\InvalidCacheKeyException;
use Lemonade\Framework\Filesystem\Contract\DirectoryManagerInterface;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Throwable;

/**
 * Persists PSR-6 values in authenticated, non-executable files below one private cache root.
 *
 * Serialized PHP values are restored only after the complete envelope and its HMAC have been verified.
 *
 * @internal Resolve the configured pool through CacheItemPoolInterface so application path boundaries are supplied.
 */
final class FileCacheItemPool implements CacheItemPoolInterface
{
    private const int FORMAT_VERSION = 1;
    private const int DEFAULT_MAX_FILE_BYTES = 8_388_608;
    private const int DEFAULT_MAX_SERIALIZATION_DEPTH = 64;
    private const int KEY_BYTES = 32;
    private const int CREATE_ATTEMPTS = 10;
    private const string FILE_PREFIX = 'lemonade-cache-v1-';
    private const string FILE_SUFFIX = '.cache';
    private const string LOCAL_KEY_FILE = '.lemonade-file-cache.key';

    /**
     * @var array<string, CacheItem>
     */
    private array $deferred = [];

    private readonly string $directory;
    private readonly string $storageRoot;
    private readonly ?string $publicRoot;
    private readonly string $keyFile;
    private readonly string $poolId;
    private ?string $integrityKey = null;

    /**
     * Binds cache persistence to a private directory and configures authenticated payload limits.
     *
     * The first two arguments preserve the original construction API. Framework-managed instances additionally
     * provide application boundaries so configured paths cannot leave storage or overlap the public web root.
     *
     * @throws CacheStorageException When a configured path or limit cannot form a safe persistence boundary
     */
    public function __construct(
        string $directory,
        private readonly DirectoryManagerInterface $directoryManager,
        private readonly ?string $applicationSecret = null,
        string $namespace = 'lemonade',
        ?string $keyFile = null,
        ?string $storageRoot = null,
        ?string $publicRoot = null,
        private readonly int $maxFileBytes = self::DEFAULT_MAX_FILE_BYTES,
        private readonly int $maxSerializationDepth = self::DEFAULT_MAX_SERIALIZATION_DEPTH,
    ) {
        if ($maxFileBytes < 1024) {
            throw new CacheStorageException('File cache maximum payload size must be at least 1024 bytes.');
        }

        if ($maxSerializationDepth < 1) {
            throw new CacheStorageException('File cache serialization depth must be positive.');
        }

        $directory = $this->assertAbsoluteLocalPath($directory, 'File cache root');
        $storageRoot ??= dirname($directory);
        $storageRoot = $this->assertAbsoluteLocalPath($storageRoot, 'File cache storage root');
        $publicRoot = $publicRoot === null
            ? null
            : $this->assertAbsoluteLocalPath($publicRoot, 'Public web root');
        $keyFile ??= $directory . DIRECTORY_SEPARATOR . self::LOCAL_KEY_FILE;
        $keyFile = $this->assertAbsoluteLocalPath($keyFile, 'File cache key path');

        $this->assertContained($directory, $storageRoot, 'File cache root escapes application storage.');
        $this->assertContained($keyFile, $storageRoot, 'File cache key escapes application storage.');

        if ($publicRoot !== null) {
            $this->assertSeparatedFromPublicRoot($directory, $publicRoot);
            $this->assertSeparatedFromPublicRoot($keyFile, $publicRoot);
        }

        $namespace = trim($namespace);
        if ($namespace === '' || str_contains($namespace, "\0")) {
            throw new CacheStorageException('File cache namespace must be a non-empty safe string.');
        }

        $this->directory = $directory;
        $this->storageRoot = $storageRoot;
        $this->publicRoot = $publicRoot;
        $this->keyFile = $keyFile;
        $this->poolId = substr(hash('sha256', $namespace), 0, 24);

        $this->assertNoSymbolicLinkComponents($this->directory);
        $this->assertNoSymbolicLinkComponents($this->keyFile);
    }

    /**
     * Loads one authenticated payload, returning a miss for absent, expired, corrupted, or modified state.
     *
     * @throws InvalidCacheKeyException
     * @throws CacheStorageException When the configured filesystem boundary becomes unsafe
     */
    public function getItem(string $key): CacheItemInterface
    {
        CacheKeyValidator::assertValid($key);

        $payload = $this->readPayload($key);

        if ($payload === null) {
            return new CacheItem($key);
        }

        if ($this->isExpired($payload['expires_at'])) {
            $this->deleteItem($key);

            return new CacheItem($key);
        }

        $value = $this->deserializeAuthenticatedValue($payload['serialized']);

        if (!$value['ok']) {
            return new CacheItem($key);
        }

        return new CacheItem(
            key: $key,
            value: $value['value'],
            hit: true,
            expiresAt: $payload['expires_at'] === null
                ? null
                : (new DateTimeImmutable())->setTimestamp($payload['expires_at']),
        );
    }

    /**
     * Returns requested entries keyed by their original cache keys.
     *
     * @throws InvalidCacheKeyException
     * @throws CacheStorageException When the configured filesystem boundary becomes unsafe
     * @return iterable<string, CacheItemInterface>
     */
    public function getItems(array $keys = []): iterable
    {
        $items = [];

        foreach ($keys as $key) {
            $items[$key] = $this->getItem($key);
        }

        return $items;
    }

    /**
     * Reports whether a live, authenticated payload exists for the validated key.
     *
     * @throws InvalidCacheKeyException
     * @throws CacheStorageException When the configured filesystem boundary becomes unsafe
     */
    public function hasItem(string $key): bool
    {
        return $this->getItem($key)->isHit();
    }

    /**
     * Removes only authenticated-format files whose names belong to this pool namespace.
     *
     * Legacy PHP files, local integrity keys, other pools, and unrelated files are left untouched.
     *
     * @throws CacheStorageException When the configured filesystem boundary becomes unsafe
     */
    public function clear(): bool
    {
        if (!file_exists($this->directory) && !is_link($this->directory)) {
            $this->deferred = [];

            return true;
        }

        $this->ensureCacheDirectory();
        $entries = @scandir($this->directory);

        if (!is_array($entries)) {
            return false;
        }

        $ok = true;

        foreach ($entries as $entry) {
            if (!$this->isOwnedCacheFilename($entry)) {
                continue;
            }

            $path = $this->directory . DIRECTORY_SEPARATOR . $entry;

            if (!$this->deleteOwnedPath($path)) {
                $ok = false;
            }
        }

        $this->deferred = [];

        return $ok;
    }

    /**
     * Removes one persisted or deferred entry without following a symbolic link at its managed path.
     *
     * @throws InvalidCacheKeyException
     * @throws CacheStorageException When the configured filesystem boundary becomes unsafe
     */
    public function deleteItem(string $key): bool
    {
        CacheKeyValidator::assertValid($key);

        $path = $this->pathForKey($key);
        $deleted = $this->deleteOwnedPath($path);
        unset($this->deferred[$key]);

        return $deleted;
    }

    /**
     * Removes each requested entry and reports whether every deletion succeeded.
     *
     * @throws InvalidCacheKeyException
     * @throws CacheStorageException When the configured filesystem boundary becomes unsafe
     */
    public function deleteItems(array $keys): bool
    {
        $ok = true;

        foreach ($keys as $key) {
            if (!$this->deleteItem($key)) {
                $ok = false;
            }
        }

        return $ok;
    }

    /**
     * Serializes and authenticates one framework cache item before atomically replacing its managed file.
     *
     * Serialization failures or values exceeding the configured file limit return false without publication.
     *
     * @throws InvalidCacheKeyException
     * @throws CacheStorageException When integrity keys, paths, or permissions cannot be secured
     */
    public function save(CacheItemInterface $item): bool
    {
        CacheKeyValidator::assertValid($item->getKey());

        if (!($item instanceof CacheItem)) {
            return false;
        }

        try {
            $serialized = serialize($item->get());
            $contents = $this->encodeEnvelope(
                key: $item->getKey(),
                expiresAt: $item->expiresAtDate()?->getTimestamp(),
                serialized: $serialized,
            );
        } catch (CacheStorageException $exception) {
            throw $exception;
        } catch (Throwable) {
            return false;
        }

        if (strlen($contents) > $this->maxFileBytes) {
            return false;
        }

        $this->ensureCacheDirectory();
        $target = $this->pathForKey($item->getKey());

        if (is_link($target) || (file_exists($target) && !is_file($target))) {
            throw new CacheStorageException('File cache target is not a safe managed file.');
        }

        [$temporary, $handle] = $this->createTemporaryFile();

        try {
            $this->writeAll($handle, $contents, $temporary);

            if (!@fflush($handle)) {
                throw new CacheStorageException('File cache payload cannot be flushed.');
            }

            if (function_exists('fsync') && !@fsync($handle)) {
                throw new CacheStorageException('File cache payload cannot be synchronized.');
            }

            $this->assertOpenRegularFile($handle, $temporary);
            $this->closeHandle($handle);
            $handle = null;

            $this->assertNoSymbolicLinkComponents($this->directory);

            if (!@rename($temporary, $target)) {
                return false;
            }

            $temporary = null;
            $this->assertSecureFile($target);
        } finally {
            $this->closeHandleQuietly($handle);

            if ($temporary !== null) {
                $this->unlinkOwnedTemporary($temporary);
            }
        }

        unset($this->deferred[$item->getKey()]);

        return true;
    }

    /**
     * Queues a framework cache item for a later commit without writing its payload yet.
     *
     * @throws InvalidCacheKeyException
     */
    public function saveDeferred(CacheItemInterface $item): bool
    {
        CacheKeyValidator::assertValid($item->getKey());

        if (!($item instanceof CacheItem)) {
            return false;
        }

        $this->deferred[$item->getKey()] = $item;

        return true;
    }

    /**
     * Writes every deferred payload and reports whether all saves succeeded.
     *
     * @throws CacheStorageException When integrity keys, paths, or permissions cannot be secured
     */
    public function commit(): bool
    {
        $ok = true;

        foreach ($this->deferred as $key => $item) {
            if (!$this->save($item)) {
                $ok = false;
            }

            unset($this->deferred[$key]);
        }

        return $ok;
    }

    /**
     * @return array{expires_at:int|null, serialized:string}|null
     */
    private function readPayload(string $key): ?array
    {
        $path = $this->pathForKey($key);

        if (!file_exists($path) && !is_link($path)) {
            return null;
        }

        $this->ensureCacheDirectory();

        if (!is_file($path) || is_link($path)) {
            return null;
        }

        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return null;
        }

        try {
            if (!@flock($handle, LOCK_SH)) {
                return null;
            }

            if (!$this->isOpenRegularFile($handle)) {
                return null;
            }

            $this->assertOpenFilePermissions($handle, $path);
            $contents = stream_get_contents($handle, $this->maxFileBytes + 1);

            if (!is_string($contents) || strlen($contents) > $this->maxFileBytes) {
                return null;
            }

            return $this->decodeEnvelope($contents, $key);
        } catch (CacheStorageException $exception) {
            throw $exception;
        } catch (Throwable) {
            return null;
        } finally {
            @flock($handle, LOCK_UN);
            @fclose($handle);
        }
    }

    private function encodeEnvelope(string $key, ?int $expiresAt, string $serialized): string
    {
        $body = json_encode(
            [
                'version' => self::FORMAT_VERSION,
                'pool' => $this->poolId,
                'key' => base64_encode($key),
                'expires_at' => $expiresAt,
                'payload' => base64_encode($serialized),
            ],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        );
        $mac = hash_hmac('sha256', $body, $this->integrityKey());

        return json_encode(
            [
                'body' => base64_encode($body),
                'mac' => $mac,
            ],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        );
    }

    /**
     * @return array{expires_at:int|null, serialized:string}|null
     */
    private function decodeEnvelope(string $contents, string $key): ?array
    {
        try {
            $envelope = json_decode($contents, true, 8, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        if (
            !is_array($envelope)
            || count($envelope) !== 2
            || !is_string($envelope['body'] ?? null)
            || !is_string($envelope['mac'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/D', $envelope['mac']) !== 1
        ) {
            return null;
        }

        $body = base64_decode($envelope['body'], true);

        if ($body === false) {
            return null;
        }

        $expectedMac = hash_hmac('sha256', $body, $this->integrityKey());

        if (!hash_equals($expectedMac, $envelope['mac'])) {
            return null;
        }

        try {
            $payload = json_decode($body, true, 8, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        if (
            !is_array($payload)
            || count($payload) !== 5
            || ($payload['version'] ?? null) !== self::FORMAT_VERSION
            || ($payload['pool'] ?? null) !== $this->poolId
            || !is_string($payload['key'] ?? null)
            || !array_key_exists('expires_at', $payload)
            || ($payload['expires_at'] !== null && !is_int($payload['expires_at']))
            || !is_string($payload['payload'] ?? null)
        ) {
            return null;
        }

        $encodedKey = base64_decode($payload['key'], true);

        if ($encodedKey === false || !hash_equals($key, $encodedKey)) {
            return null;
        }

        $serialized = base64_decode($payload['payload'], true);

        if ($serialized === false) {
            return null;
        }

        return [
            'expires_at' => $payload['expires_at'],
            'serialized' => $serialized,
        ];
    }

    /**
     * @return array{ok:bool, value:mixed}
     */
    private function deserializeAuthenticatedValue(string $serialized): array
    {
        set_error_handler(static function (): bool {
            return true;
        });

        try {
            $value = unserialize(
                $serialized,
                [
                    'allowed_classes' => true,
                    'max_depth' => $this->maxSerializationDepth,
                ],
            );
        } catch (Throwable) {
            return [
                'ok' => false,
                'value' => null,
            ];
        } finally {
            restore_error_handler();
        }

        if ($value === false && $serialized !== serialize(false)) {
            return [
                'ok' => false,
                'value' => null,
            ];
        }

        return [
            'ok' => true,
            'value' => $value,
        ];
    }

    private function integrityKey(): string
    {
        if ($this->integrityKey !== null) {
            return $this->integrityKey;
        }

        $secret = $this->applicationSecret;

        if ($secret === null || $secret === '') {
            $secret = $this->loadOrCreateLocalKey();
        }

        $this->integrityKey = hash_hkdf(
            'sha256',
            $secret,
            self::KEY_BYTES,
            'lemonade.file-cache.pool.v1:' . $this->poolId,
        );

        return $this->integrityKey;
    }

    private function loadOrCreateLocalKey(): string
    {
        $this->ensureStorageRoot();
        $this->ensurePrivateDirectory(dirname($this->keyFile));
        $this->assertNoSymbolicLinkComponents($this->keyFile);

        if (is_link($this->keyFile) || (file_exists($this->keyFile) && !is_file($this->keyFile))) {
            throw new CacheStorageException('File cache integrity key path is unsafe.');
        }

        $handle = @fopen($this->keyFile, 'x+b');

        if ($handle === false) {
            $handle = @fopen($this->keyFile, 'r+b');
        }

        if ($handle === false) {
            throw new CacheStorageException('File cache integrity key cannot be opened.');
        }

        try {
            if (!@flock($handle, LOCK_EX)) {
                throw new CacheStorageException('File cache integrity key cannot be locked.');
            }

            $this->assertOpenRegularFile($handle, $this->keyFile);
            $this->secureOpenFilePermissions($handle, $this->keyFile);
            $stat = @fstat($handle);

            if (!is_array($stat)) {
                throw new CacheStorageException('File cache integrity key cannot be inspected.');
            }

            if ($stat['size'] === 0) {
                $this->initializeLocalKey($handle);
            } elseif ($stat['size'] !== self::KEY_BYTES) {
                throw new CacheStorageException('File cache integrity key has an invalid size.');
            }

            if (@rewind($handle) === false) {
                throw new CacheStorageException('File cache integrity key cannot be read.');
            }

            $key = stream_get_contents($handle, self::KEY_BYTES + 1);

            if (!is_string($key) || strlen($key) !== self::KEY_BYTES) {
                throw new CacheStorageException('File cache integrity key is incomplete.');
            }

            return $key;
        } finally {
            @flock($handle, LOCK_UN);
            @fclose($handle);
        }
    }

    /**
     * @param resource $handle
     */
    private function initializeLocalKey($handle): void
    {
        try {
            $key = random_bytes(self::KEY_BYTES);
        } catch (Throwable $exception) {
            throw new CacheStorageException('File cache integrity key cannot be generated.', previous: $exception);
        }

        if (!@ftruncate($handle, 0) || @rewind($handle) === false) {
            throw new CacheStorageException('File cache integrity key cannot be initialized.');
        }

        $this->writeAll($handle, $key, $this->keyFile);

        if (!@fflush($handle)) {
            throw new CacheStorageException('File cache integrity key cannot be flushed.');
        }

        if (function_exists('fsync') && !@fsync($handle)) {
            throw new CacheStorageException('File cache integrity key cannot be synchronized.');
        }
    }

    private function pathForKey(string $key): string
    {
        $keyHash = hash('sha256', $this->poolId . "\0" . $key);

        return sprintf(
            '%s%s%s-%s%s',
            $this->directory,
            DIRECTORY_SEPARATOR,
            self::FILE_PREFIX . $this->poolId,
            $keyHash,
            self::FILE_SUFFIX,
        );
    }

    private function ensureCacheDirectory(): void
    {
        $this->ensureStorageRoot();
        $this->ensurePrivateDirectory($this->directory);
        $this->assertSeparatedFromConfiguredPublicRoot($this->directory);
    }

    private function ensureStorageRoot(): void
    {
        if (!is_dir($this->storageRoot)) {
            $this->ensurePrivateDirectory($this->storageRoot);
        }

        if (is_link($this->storageRoot) || !is_dir($this->storageRoot)) {
            throw new CacheStorageException('File cache storage root is not a safe directory.');
        }

        clearstatcache(true, $this->storageRoot);
        $permissions = @fileperms($this->storageRoot);

        if (!is_int($permissions)) {
            throw new CacheStorageException('File cache storage root permissions cannot be inspected.');
        }

        if (($permissions & 0022) !== 0) {
            $securedMode = ($permissions & 0777) & ~0022;

            if (!@chmod($this->storageRoot, $securedMode)) {
                throw new CacheStorageException('File cache storage root permissions cannot be restricted.');
            }

            clearstatcache(true, $this->storageRoot);
            $permissions = @fileperms($this->storageRoot);

            if (!is_int($permissions) || ($permissions & 0022) !== 0) {
                throw new CacheStorageException('File cache storage root remains writable by other users.');
            }
        }
    }

    private function ensurePrivateDirectory(string $directory): void
    {
        $this->assertContained($directory, $this->storageRoot, 'File cache directory escapes application storage.');
        $this->assertSeparatedFromConfiguredPublicRoot($directory);
        $this->assertNoSymbolicLinkComponents($directory);

        if (!is_dir($directory)) {
            try {
                $this->directoryManager->create($directory, 0700);
            } catch (Throwable $exception) {
                throw new CacheStorageException('File cache directory cannot be created securely.', previous: $exception);
            }
        }

        if (is_link($directory) || !is_dir($directory)) {
            throw new CacheStorageException('File cache directory is unsafe.');
        }

        if (!@chmod($directory, 0700)) {
            throw new CacheStorageException('File cache directory permissions cannot be restricted.');
        }

        clearstatcache(true, $directory);
        $permissions = @fileperms($directory);

        if (!is_int($permissions) || ($permissions & 0777) !== 0700) {
            throw new CacheStorageException('File cache directory permissions are unsafe.');
        }

        $this->assertNoSymbolicLinkComponents($directory);
        $this->assertCanonicalContainment($directory, $this->storageRoot);
    }

    /**
     * @return array{0:string, 1:resource}
     */
    private function createTemporaryFile(): array
    {
        $previous = null;

        for ($attempt = 0; $attempt < self::CREATE_ATTEMPTS; ++$attempt) {
            try {
                $path = sprintf(
                    '%s%s.lemonade-cache-write-%s-%s.tmp',
                    $this->directory,
                    DIRECTORY_SEPARATOR,
                    $this->poolId,
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
                    throw new CacheStorageException('File cache temporary file cannot be locked.');
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

        throw new CacheStorageException('File cache temporary file cannot be created.', previous: $previous);
    }

    /**
     * @param resource $handle
     */
    private function writeAll($handle, string $contents, string $path): void
    {
        $offset = 0;
        $length = strlen($contents);

        while ($offset < $length) {
            $written = @fwrite($handle, substr($contents, $offset));

            if (!is_int($written) || $written <= 0) {
                throw new CacheStorageException(sprintf('File cache data cannot be written to "%s".', $path));
            }

            $offset += $written;
        }
    }

    /**
     * @param resource $handle
     */
    private function secureOpenFilePermissions($handle, string $path): void
    {
        if (!$this->openFileMatchesPath($handle, $path)) {
            throw new CacheStorageException(sprintf('File cache file "%s" identity cannot be verified.', $path));
        }

        if (!@chmod($path, 0600)) {
            throw new CacheStorageException(sprintf('File cache file "%s" cannot be restricted to mode 0600.', $path));
        }

        $stat = @fstat($handle);

        if (
            !$this->openFileMatchesPath($handle, $path)
            || !is_array($stat)
            || ($stat['mode'] & 0777) !== 0600
        ) {
            throw new CacheStorageException(sprintf('File cache file "%s" has unsafe permissions.', $path));
        }
    }

    /**
     * @param resource $handle
     */
    private function assertOpenFilePermissions($handle, string $path): void
    {
        $stat = @fstat($handle);

        if (!is_array($stat) || ($stat['mode'] & 0777) !== 0600) {
            throw new CacheStorageException(sprintf('File cache file "%s" has unsafe permissions.', $path));
        }
    }

    private function assertSecureFile(string $path): void
    {
        clearstatcache(true, $path);
        $stat = @lstat($path);

        if (
            !is_array($stat)
            || is_link($path)
            || ($stat['mode'] & 0170000) !== 0100000
            || ($stat['mode'] & 0777) !== 0600
        ) {
            throw new CacheStorageException('Published file cache payload is unsafe.');
        }
    }

    /**
     * @param resource $handle
     */
    private function assertOpenRegularFile($handle, string $path): void
    {
        if (!$this->openFileMatchesPath($handle, $path)) {
            throw new CacheStorageException('File cache file identity cannot be verified.');
        }
    }

    /**
     * @param resource $handle
     */
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

    /**
     * @param resource $handle
     */
    private function isOpenRegularFile($handle): bool
    {
        $opened = @fstat($handle);

        return is_array($opened) && ($opened['mode'] & 0170000) === 0100000;
    }

    private function deleteOwnedPath(string $path): bool
    {
        if (!file_exists($path) && !is_link($path)) {
            return true;
        }

        $this->ensureCacheDirectory();

        if (is_dir($path) && !is_link($path)) {
            return false;
        }

        return @unlink($path);
    }

    private function unlinkOwnedTemporary(string $path): void
    {
        $prefix = $this->directory
            . DIRECTORY_SEPARATOR
            . '.lemonade-cache-write-'
            . $this->poolId
            . '-';

        if (!str_starts_with($path, $prefix) || !str_ends_with($path, '.tmp')) {
            return;
        }

        @unlink($path);
    }

    private function isOwnedCacheFilename(string $filename): bool
    {
        return preg_match(
            '/^'
            . preg_quote(self::FILE_PREFIX . $this->poolId . '-', '/')
            . '[a-f0-9]{64}'
            . preg_quote(self::FILE_SUFFIX, '/')
            . '$/D',
            $filename,
        ) === 1;
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
            throw new CacheStorageException($label . ' must be an absolute local path without traversal.');
        }

        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }

    private function assertContained(string $path, string $root, string $message): void
    {
        $path = $this->comparablePath($path);
        $root = $this->comparablePath($root);

        if ($path !== $root && !str_starts_with($path, $root . DIRECTORY_SEPARATOR)) {
            throw new CacheStorageException($message);
        }
    }

    private function assertCanonicalContainment(string $path, string $root): void
    {
        $resolvedPath = @realpath($path);
        $resolvedRoot = @realpath($root);

        if (!is_string($resolvedPath) || !is_string($resolvedRoot)) {
            throw new CacheStorageException('File cache canonical storage boundary cannot be resolved.');
        }

        $this->assertContained(
            $resolvedPath,
            $resolvedRoot,
            'File cache path escapes its canonical storage boundary.',
        );
    }

    private function assertSeparatedFromConfiguredPublicRoot(string $path): void
    {
        if ($this->publicRoot !== null) {
            $this->assertSeparatedFromPublicRoot($path, $this->publicRoot);
        }
    }

    private function assertSeparatedFromPublicRoot(string $path, string $publicRoot): void
    {
        $path = $this->comparablePath($path);
        $publicRoot = $this->comparablePath($publicRoot);
        $pathContainsPublic = $publicRoot === $path
            || str_starts_with($publicRoot, $path . DIRECTORY_SEPARATOR);
        $publicContainsPath = $path === $publicRoot
            || str_starts_with($path, $publicRoot . DIRECTORY_SEPARATOR);

        if ($pathContainsPublic || $publicContainsPath) {
            throw new CacheStorageException('File cache storage must be separated from the public web root.');
        }
    }

    private function assertNoSymbolicLinkComponents(string $path): void
    {
        $cursor = $path;
        $root = $this->storageRoot;

        while (true) {
            if (is_link($cursor)) {
                throw new CacheStorageException('File cache paths cannot contain symbolic links.');
            }

            if ($this->comparablePath($cursor) === $this->comparablePath($root)) {
                return;
            }

            $parent = dirname($cursor);

            if ($parent === $cursor) {
                throw new CacheStorageException('File cache path escapes application storage.');
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

    /**
     * @param resource|null $handle
     */
    private function closeHandleQuietly(mixed $handle): void
    {
        if (!is_resource($handle)) {
            return;
        }

        @flock($handle, LOCK_UN);
        @fclose($handle);
    }

    /**
     * @param resource $handle
     */
    private function closeHandle($handle): void
    {
        @flock($handle, LOCK_UN);

        if (!@fclose($handle)) {
            throw new CacheStorageException('File cache payload handle cannot be closed.');
        }
    }

    private function isExpired(?int $expiresAt): bool
    {
        return $expiresAt !== null && $expiresAt <= time();
    }
}
