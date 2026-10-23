<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Bags;

use Lemonade\Framework\Http\Request\ServerHelper;

/**
 * Preserves the server-bag API while delegating trusted values to the central request policy.
 */
final readonly class ServerBag
{
    /**
     * Binds the bag to one request-scoped server helper.
     */
    public function __construct(
        private ServerHelper $server,
    ) {
    }

    /**
     * Returns the raw server-parameter snapshot from the explicit PSR request.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->server->all();
    }

    /**
     * Returns one raw server parameter or the supplied fallback when absent.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $items = $this->server->all();

        return $items[$key] ?? $default;
    }

    /**
     * Returns the allowlisted host or the explicit fallback when no host was trusted.
     */
    public function host(string $default = 'localhost'): string
    {
        return $this->server->host($default);
    }

    /**
     * Returns the scheme established by the central request trust policy.
     *
     * @return 'http'|'https'
     */
    public function scheme(): string
    {
        return $this->server->scheme();
    }

    /**
     * Returns the verified client address or the explicit fallback when unavailable.
     */
    public function ip(?string $default = null): ?string
    {
        return $this->server->clientIp($default);
    }
}
