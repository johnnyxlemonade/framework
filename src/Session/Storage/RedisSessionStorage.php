<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Storage;

use Redis;
use RuntimeException;

final class RedisSessionStorage implements SessionStorageInterface
{
    private bool $started = false;

    private string $sessionId = '';

    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    public function __construct(
        private readonly string $host = '127.0.0.1',
        private readonly int $port = 6379,
        private readonly int $database = 0,
        private readonly ?string $password = null,
        private readonly string $prefix = 'sess:',
        private readonly int $lifetimeSeconds = 7200,
        private readonly string $cookieName = 'LEMONADE_SESSION',
        private readonly float $timeout = 2.5,
    ) {}

    public function start(): void
    {
        if ($this->started) {
            return;
        }

        $this->sessionId = $this->resolveSessionId();
        $this->data = $this->readSession($this->sessionId);
        $this->touchSession();
        $this->sendCookie();

        $this->started = true;
    }

    public function started(): bool
    {
        return $this->started;
    }

    public function has(string $key): bool
    {
        $this->start();

        return array_key_exists($key, $this->data);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();

        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();

        $this->data[$key] = $value;
        $this->writeSession();
    }

    public function remove(string $key): void
    {
        $this->start();

        unset($this->data[$key]);
        $this->writeSession();
    }

    public function clear(): void
    {
        $this->start();

        $this->data = [];
        $this->writeSession();
    }

    public function regenerate(bool $deleteOldSession = true): void
    {
        $this->start();

        $redis = $this->connect();

        try {
            $oldSessionId = $this->sessionId;
            $oldKey = $this->key($oldSessionId);

            $this->sessionId = $this->generateSessionId();
            $this->writeSession();
            $this->sendCookie();

            if ($deleteOldSession) {
                $redis->del($oldKey);
            }
        } finally {
            $redis->close();
        }
    }

    private function resolveSessionId(): string
    {
        $sessionId = $_COOKIE[$this->cookieName] ?? null;

        if (is_string($sessionId) && preg_match('/^[a-f0-9]{64}$/', $sessionId) === 1) {
            return $sessionId;
        }

        return $this->generateSessionId();
    }

    private function generateSessionId(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * @return array<string, mixed>
     */
    private function readSession(string $sessionId): array
    {
        $redis = $this->connect();

        try {
            $value = $redis->get($this->key($sessionId));

            if (!is_string($value) || $value === '') {
                return [];
            }

            $data = unserialize($value, ['allowed_classes' => false]);

            if (!is_array($data)) {
                return [];
            }

            $normalized = [];

            foreach ($data as $key => $item) {
                if (is_string($key)) {
                    $normalized[$key] = $item;
                }
            }

            return $normalized;
        } finally {
            $redis->close();
        }
    }

    private function writeSession(): void
    {
        $redis = $this->connect();

        try {
            $payload = serialize($this->data);
            $ok = $redis->setex($this->key($this->sessionId), $this->lifetimeSeconds, $payload);

            if ($ok !== true) {
                throw new RuntimeException('Failed to write redis session payload.');
            }
        } finally {
            $redis->close();
        }
    }

    private function touchSession(): void
    {
        $redis = $this->connect();

        try {
            $key = $this->key($this->sessionId);
            $existsResult = $redis->exists($key);
            $exists = is_int($existsResult) ? $existsResult > 0 : $existsResult === true;

            if ($exists) {
                $redis->expire($key, $this->lifetimeSeconds);

                return;
            }

            $payload = serialize($this->data);
            $redis->setex($key, $this->lifetimeSeconds, $payload);
        } finally {
            $redis->close();
        }
    }

    private function key(string $sessionId): string
    {
        return $this->prefix . $sessionId;
    }

    private function connect(): Redis
    {
        if (!class_exists(Redis::class)) {
            throw new RuntimeException(
                'Redis extension is not installed. Install ext-redis to use redis session storage.',
            );
        }

        $redis = new Redis();
        $connected = $redis->connect($this->host, $this->port, $this->timeout);

        if ($connected !== true) {
            throw new RuntimeException('Failed to connect to redis session storage.');
        }

        if ($this->password !== null && $this->password !== '') {
            if ($redis->auth($this->password) !== true) {
                throw new RuntimeException('Redis authentication failed for session storage.');
            }
        }

        if ($this->database > 0 && $redis->select($this->database) !== true) {
            throw new RuntimeException(sprintf(
                'Failed to select redis database %d for session storage.',
                $this->database,
            ));
        }

        return $redis;
    }

    private function sendCookie(): void
    {
        if (headers_sent()) {
            return;
        }

        setcookie($this->cookieName, $this->sessionId, [
            'expires' => time() + $this->lifetimeSeconds,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
