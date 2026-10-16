<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Request;

/**
 * Provides normalized access to request metadata and canonical scheme and host resolution.
 *
 * Forwarded scheme metadata is consumed as supplied; the hosting environment owns its trust boundary.
 */
final class ServerHelper
{
    /**
     * Returns one string server value or the supplied fallback.
     */
    public static function get(string $key, string $default = ''): string
    {
        $value = $_SERVER[$key] ?? $default;

        return is_string($value) ? $value : $default;
    }

    /**
     * Reports whether one non-empty string server value is available.
     */
    public static function has(string $key): bool
    {
        return isset($_SERVER[$key])
            && is_string($_SERVER[$key])
            && $_SERVER[$key] !== '';
    }

    /**
     * Returns server metadata with non-string keys removed.
     *
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        $normalized = [];

        foreach ($_SERVER as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * Resolves the request host from HTTP_HOST, then SERVER_NAME, or returns the fallback.
     */
    public static function host(string $default = 'localhost'): string
    {
        $host = trim(self::get('HTTP_HOST'));

        if ($host !== '') {
            return $host;
        }

        $serverName = trim(self::get('SERVER_NAME'));

        if ($serverName !== '') {
            return $serverName;
        }

        return $default;
    }

    /**
     * Resolves HTTP or HTTPS, preferring the first forwarded protocol when it is recognized.
     *
     * @return 'http'|'https'
     */
    public static function scheme(): string
    {
        $forwardedProto = strtolower(trim(self::get('HTTP_X_FORWARDED_PROTO')));

        if ($forwardedProto !== '') {
            $scheme = trim(explode(',', $forwardedProto)[0]);

            if ($scheme === 'https' || $scheme === 'http') {
                return $scheme;
            }
        }

        return self::isHttps() ? 'https' : 'http';
    }

    /**
     * Reports whether direct server metadata identifies the current request as HTTPS.
     */
    public static function isHttps(): bool
    {
        $https = strtolower(trim(self::get('HTTPS')));

        return ($https !== '' && $https !== 'off' && $https !== '0')
            || self::get('SERVER_PORT') === '443';
    }
}
