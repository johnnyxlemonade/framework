<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Storage;

use Closure;
use Lemonade\Framework\Session\SessionIdGenerator;
use RuntimeException;

/**
 * Persists sliding sessions in Redis and delegates physical cleanup to key TTL.
 *
 * Concurrent payload mutations use last-writer-wins semantics.
 */
final class RedisSessionStorage implements SessionStorageInterface
{
    private bool $started = false;
    private string $sessionId = '';

    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    private readonly SessionCookie $cookie;
    private readonly SessionIdGenerator $sessionIdGenerator;

    /**
     * @var Closure(): RedisSessionClientInterface
     */
    private readonly Closure $clientFactory;

    /**
     * Defines the Redis endpoint, key namespace, sliding retention, and browser identity.
     *
     * @param null|Closure(): RedisSessionClientInterface $clientFactory
     */
    public function __construct(
        private readonly string $host = '127.0.0.1',
        private readonly int $port = 6379,
        private readonly int $database = 0,
        private readonly ?string $password = null,
        private readonly string $prefix = 'sess:',
        private readonly int $lifetimeSeconds = 7200,
        private readonly string $cookieName = 'LEMONADE_SESSION',
        private readonly float $timeout = 2.5,
        ?SessionCookie $cookie = null,
        ?Closure $clientFactory = null,
    ) {
        $this->cookie = $cookie ?? new SessionCookie($cookieName, $lifetimeSeconds);
        $this->clientFactory = $clientFactory ?? static fn(): RedisSessionClientInterface => new PhpRedisSessionClient();
        $this->sessionIdGenerator = new SessionIdGenerator();
    }

    /**
     * Loads and touches a live key or replaces a missing identity with a fresh TTL-backed key.
     */
    public function start(): void
    {
        if ($this->started) {
            return;
        }

        $this->cookie->assertCanChange();
        $redis = $this->connect();

        try {
            $candidate = $this->resolveSessionId();

            if ($candidate === null) {
                $this->startNewSession($redis);
            } else {
                $payload = $redis->get($this->key($candidate));
                $data = $payload === null ? null : $this->decodePayload($payload);

                if ($data === null) {
                    if ($payload !== null) {
                        $this->deleteAndVerify($redis, $this->key($candidate));
                    }

                    $this->startNewSession($redis);
                } elseif (!$redis->expire($this->key($candidate), $this->lifetimeSeconds)) {
                    $this->deleteAndVerify($redis, $this->key($candidate));
                    $this->startNewSession($redis);
                } else {
                    $this->sessionId = $candidate;
                    $this->data = $data;
                }
            }

            $this->cookie->send($this->sessionId);
            $this->started = true;
        } finally {
            $redis->close();
        }
    }

    /**
     * Reports whether this request has established its Redis-backed identity.
     */
    public function started(): bool
    {
        return $this->started;
    }

    /**
     * Checks the payload loaded for this request.
     */
    public function has(string $key): bool
    {
        $this->start();

        return array_key_exists($key, $this->data);
    }

    /**
     * Returns a loaded value or the supplied fallback.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();

        return array_key_exists($key, $this->data)
            ? $this->data[$key]
            : $default;
    }

    /**
     * Stores a value with a renewed Redis TTL.
     */
    public function set(string $key, mixed $value): void
    {
        $this->start();

        $this->data[$key] = $value;
        $this->writeSession();
    }

    /**
     * Removes one value and renews the Redis TTL.
     */
    public function remove(string $key): void
    {
        $this->start();

        unset($this->data[$key]);
        $this->writeSession();
    }

    /**
     * Persists an empty payload while retaining the active key and cookie.
     */
    public function clear(): void
    {
        $this->start();

        $this->data = [];
        $this->writeSession();
    }

    /**
     * Deletes and verifies the active key, then expires its browser cookie.
     */
    public function destroy(): void
    {
        $this->start();
        $this->cookie->assertCanChange();

        $redis = $this->connect();

        try {
            $this->deleteAndVerify($redis, $this->key($this->sessionId));
            $this->cookie->expire();
            $this->reset();
        } finally {
            $redis->close();
        }
    }

    /**
     * Copies the payload to a fresh TTL-backed key and optionally invalidates the old key.
     */
    public function regenerate(bool $deleteOldSession = true): void
    {
        $this->start();
        $this->cookie->assertCanChange();

        $redis = $this->connect();

        try {
            $oldKey = $this->key($this->sessionId);
            $this->sessionId = $this->sessionIdGenerator->generate();
            $this->writeSessionWith($redis);

            if ($deleteOldSession) {
                $this->deleteAndVerify($redis, $oldKey);
            }

            $this->cookie->send($this->sessionId);
        } finally {
            $redis->close();
        }
    }

    private function reset(): void
    {
        $this->started = false;
        $this->sessionId = '';
        $this->data = [];
    }

    private function resolveSessionId(): ?string
    {
        $sessionId = $_COOKIE[$this->cookieName] ?? null;

        if (is_string($sessionId) && $this->sessionIdGenerator->isValid($sessionId)) {
            return $sessionId;
        }

        return null;
    }

    private function startNewSession(RedisSessionClientInterface $redis): void
    {
        $this->sessionId = $this->sessionIdGenerator->generate();
        $this->data = [];
        $this->writeSessionWith($redis);
    }

    /**
     * @return null|array<string, mixed>
     */
    private function decodePayload(string $payload): ?array
    {
        if ($payload === '') {
            return null;
        }

        $data = @unserialize($payload, ['allowed_classes' => false]);

        if (!is_array($data)) {
            return null;
        }

        $normalized = [];

        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    private function writeSession(): void
    {
        $redis = $this->connect();

        try {
            $this->writeSessionWith($redis);
        } finally {
            $redis->close();
        }
    }

    private function writeSessionWith(RedisSessionClientInterface $redis): void
    {
        $payload = serialize($this->data);

        if (!$redis->setex($this->key($this->sessionId), $this->lifetimeSeconds, $payload)) {
            throw new RuntimeException('Failed to write Redis session payload with expiry.');
        }
    }

    private function deleteAndVerify(RedisSessionClientInterface $redis, string $key): void
    {
        if (!$redis->delete($key) || $redis->exists($key)) {
            throw new RuntimeException(sprintf(
                'Failed to invalidate Redis session key "%s".',
                $key,
            ));
        }
    }

    private function key(string $sessionId): string
    {
        return $this->prefix . $sessionId;
    }

    private function connect(): RedisSessionClientInterface
    {
        $redis = ($this->clientFactory)();

        if (!$redis instanceof RedisSessionClientInterface) {
            throw new RuntimeException('Redis session client factory returned an incompatible client.');
        }
        if (!$redis->connect($this->host, $this->port, $this->timeout)) {
            throw new RuntimeException('Failed to connect to Redis session storage.');
        }
        if ($this->password !== null && $this->password !== '' && !$redis->authenticate($this->password)) {
            throw new RuntimeException('Redis authentication failed for session storage.');
        }
        if ($this->database > 0 && !$redis->selectDatabase($this->database)) {
            throw new RuntimeException(sprintf(
                'Failed to select Redis database %d for session storage.',
                $this->database,
            ));
        }

        return $redis;
    }
}
