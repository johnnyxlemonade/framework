<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cache\Store;

use DateTimeImmutable;
use Lemonade\Framework\Cache\CacheKeyValidator;
use Lemonade\Framework\Cache\Exception\InvalidCacheKeyException;
use Lemonade\Framework\Filesystem\Contract\DirectoryManagerInterface;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Throwable;

use function array_key_exists;
use function file_put_contents;
use function glob;
use function is_array;
use function is_file;
use function is_int;
use function is_string;
use function serialize;
use function set_error_handler;
use function sha1;
use function time;
use function uniqid;
use function unserialize;
use function var_export;

use const DIRECTORY_SEPARATOR;
use const LOCK_EX;

final class FileCacheItemPool implements CacheItemPoolInterface
{
    /**
     * @var array<string, CacheItem>
     */
    private array $deferred = [];

    public function __construct(
        private readonly string $directory,
        private readonly DirectoryManagerInterface $directoryManager,
    ) {}

    /**
     * @throws InvalidCacheKeyException
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

        $value = $this->deserializeValue($payload['value']);

        if (!$value['ok']) {
            $this->deleteItem($key);

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
     * @throws InvalidCacheKeyException
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
     * @throws InvalidCacheKeyException
     */
    public function hasItem(string $key): bool
    {
        return $this->getItem($key)->isHit();
    }

    public function clear(): bool
    {
        if (!is_dir($this->directory)) {
            return true;
        }

        $files = glob($this->directory . DIRECTORY_SEPARATOR . '*.php');

        if (!is_array($files)) {
            return true;
        }

        $ok = true;

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            try {
                $this->directoryManager->delete($file);
            } catch (Throwable) {
                $ok = false;
            }
        }

        $this->deferred = [];

        return $ok;
    }

    /**
     * @throws InvalidCacheKeyException
     */
    public function deleteItem(string $key): bool
    {
        CacheKeyValidator::assertValid($key);

        $path = $this->pathForKey($key);

        if (is_file($path)) {
            try {
                $this->directoryManager->delete($path);
            } catch (Throwable) {
                return false;
            }
        }

        unset($this->deferred[$key]);

        return true;
    }

    /**
     * @throws InvalidCacheKeyException
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
     * @throws InvalidCacheKeyException
     */
    public function save(CacheItemInterface $item): bool
    {
        CacheKeyValidator::assertValid($item->getKey());

        if (!($item instanceof CacheItem)) {
            return false;
        }

        try {
            $this->ensureDirectory();
        } catch (Throwable) {
            return false;
        }

        $payload = [
            'key' => $item->getKey(),
            'expires_at' => $item->expiresAtDate()?->getTimestamp(),
            'value' => serialize($item->get()),
        ];

        $contents = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($payload, true) . ";\n";
        $target = $this->pathForKey($item->getKey());
        $tmp = $target . '.' . uniqid('tmp', true);

        if (file_put_contents($tmp, $contents, LOCK_EX) === false) {
            $this->deleteTemporaryFile($tmp);

            return false;
        }

        if (!rename($tmp, $target)) {
            $this->deleteTemporaryFile($tmp);

            return false;
        }

        unset($this->deferred[$item->getKey()]);

        return true;
    }

    /**
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
     * @return array{key:string, expires_at:int|null, value:string}|null
     */
    private function readPayload(string $key): ?array
    {
        $path = $this->pathForKey($key);

        if (!is_file($path)) {
            return null;
        }

        try {
            $payload = require $path;
        } catch (Throwable) {
            $this->deleteCorruptedPayload($path);

            return null;
        }

        if (!is_array($payload)) {
            return null;
        }

        if (
            !array_key_exists('key', $payload)
            || !array_key_exists('expires_at', $payload)
            || !array_key_exists('value', $payload)
            || !is_string($payload['key'])
            || !is_string($payload['value'])
            || ($payload['expires_at'] !== null && !is_int($payload['expires_at']))
        ) {
            return null;
        }

        if ($payload['key'] !== $key) {
            return null;
        }

        return [
            'key' => $payload['key'],
            'expires_at' => $payload['expires_at'],
            'value' => $payload['value'],
        ];
    }

    /**
     * @return array{ok:bool, value:mixed}
     */
    private function deserializeValue(string $serialized): array
    {
        $previousHandler = set_error_handler(static function (): bool {
            return true;
        });

        try {
            $value = unserialize($serialized, ['allowed_classes' => true]);
        } catch (Throwable) {
            return [
                'ok' => false,
                'value' => null,
            ];
        } finally {
            if ($previousHandler !== null) {
                set_error_handler($previousHandler);
            } else {
                restore_error_handler();
            }
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

    private function pathForKey(string $key): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . sha1($key) . '.php';
    }

    private function ensureDirectory(): void
    {
        $this->directoryManager->create($this->directory, 0775);
    }

    private function deleteTemporaryFile(string $file): void
    {
        if (!is_file($file)) {
            return;
        }

        try {
            $this->directoryManager->delete($file);
        } catch (Throwable) {
            // Temporary cache file cleanup must not break save() return semantics.
        }
    }

    private function deleteCorruptedPayload(string $file): void
    {
        if (!is_file($file)) {
            return;
        }

        try {
            $this->directoryManager->delete($file);
        } catch (Throwable) {
            // Corrupted cache payload still resolves as cache miss.
        }
    }

    private function isExpired(?int $expiresAt): bool
    {
        return $expiresAt !== null && $expiresAt <= time();
    }
}
