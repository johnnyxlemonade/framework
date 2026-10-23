<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Request\Internal;

use InvalidArgumentException;

/**
 * Compiles exact hostnames and bounded suffix wildcards for request-origin validation.
 *
 * @internal
 */
final readonly class AllowedHostMatcher
{
    /**
     * @var list<array{host: non-empty-string, wildcard: bool}>
     */
    private array $patterns;

    /**
     * Validates and compiles host patterns without interpreting an empty list as unrestricted access.
     *
     * @param list<string> $allowedHosts
     *
     * @throws InvalidArgumentException When a pattern is malformed or uses an unsupported wildcard.
     */
    public function __construct(array $allowedHosts = [])
    {
        $patterns = [];

        foreach ($allowedHosts as $allowedHost) {
            if (trim($allowedHost) !== $allowedHost) {
                throw new InvalidArgumentException(sprintf(
                    'Allowed host pattern "%s" must not contain surrounding whitespace.',
                    $allowedHost,
                ));
            }

            $pattern = strtolower($allowedHost);
            $wildcard = str_starts_with($pattern, '*.');
            $host = $wildcard ? substr($pattern, 2) : $pattern;

            if ($host === '' || str_contains($host, '*') || !$this->isValidHost($host)) {
                throw new InvalidArgumentException(sprintf(
                    'Allowed host pattern "%s" must be an exact hostname, IP address, or leading suffix wildcard.',
                    $allowedHost,
                ));
            }

            if ($wildcard && filter_var($host, FILTER_VALIDATE_IP) !== false) {
                throw new InvalidArgumentException(sprintf(
                    'Allowed host pattern "%s" cannot apply a wildcard to an IP address.',
                    $allowedHost,
                ));
            }

            $normalizedHost = $this->normalizeHost($host);
            if ($normalizedHost === '') {
                throw new InvalidArgumentException('Allowed host patterns must not normalize to an empty value.');
            }

            $patterns[] = [
                'host' => $normalizedHost,
                'wildcard' => $wildcard,
            ];
        }

        $this->patterns = $patterns;
    }

    /**
     * Reports whether a normalized request host matches one explicit pattern.
     */
    public function allows(string $host): bool
    {
        if (!$this->isValidHost($host)) {
            return false;
        }

        $normalized = $this->normalizeHost($host);

        foreach ($this->patterns as $pattern) {
            if (!$pattern['wildcard'] && $normalized === $pattern['host']) {
                return true;
            }

            if (
                $pattern['wildcard']
                && $normalized !== $pattern['host']
                && str_ends_with($normalized, '.' . $pattern['host'])
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reports whether a host is an IP literal or a canonical ASCII DNS hostname.
     */
    public function isValidHost(string $host): bool
    {
        $normalized = $this->normalizeHost($host);
        if ($normalized === '') {
            return false;
        }

        if (filter_var($normalized, FILTER_VALIDATE_IP) !== false) {
            return true;
        }

        if (strlen($normalized) > 253) {
            return false;
        }

        foreach (explode('.', $normalized) as $label) {
            if (
                $label === ''
                || strlen($label) > 63
                || preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $label) !== 1
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Canonicalizes case and one DNS root marker before comparison.
     */
    public function normalizeHost(string $host): string
    {
        return strtolower(rtrim(trim($host), '.'));
    }
}
