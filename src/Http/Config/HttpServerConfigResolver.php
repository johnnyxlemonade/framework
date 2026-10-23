<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Config;

use InvalidArgumentException;
use Lemonade\Framework\Http\Request\Internal\AllowedHostMatcher;
use Lemonade\Framework\Http\Request\TrustedProxyMatcher;

/**
 * Merges inbound server definitions into one validated fail-closed trust policy.
 */
final readonly class HttpServerConfigResolver
{
    /**
     * Resolves later definitions over secure defaults and rejects ambiguous trust configuration.
     */
    public function resolve(HttpServerConfigDefinition ...$definitions): HttpServerConfig
    {
        $trustedProxies = [];
        $profile = ForwardedHeaderProfile::None;
        $trustForwardedPrefix = false;
        $allowedHosts = [];

        foreach ($definitions as $definition) {
            $data = $definition->toArray();

            if (array_key_exists('trusted_proxies', $data)) {
                $trustedProxies = $this->stringList($data['trusted_proxies'], 'trusted_proxies');
            }

            if (array_key_exists('forwarded_header_profile', $data)) {
                $profile = $this->profile($data['forwarded_header_profile']);
            }

            if (array_key_exists('trust_forwarded_prefix', $data)) {
                if (!is_bool($data['trust_forwarded_prefix'])) {
                    throw new InvalidArgumentException('HTTP server trust_forwarded_prefix must be a boolean.');
                }

                $trustForwardedPrefix = $data['trust_forwarded_prefix'];
            }

            if (array_key_exists('allowed_hosts', $data)) {
                $allowedHosts = $this->stringList($data['allowed_hosts'], 'allowed_hosts');
            }
        }

        new TrustedProxyMatcher($trustedProxies);
        new AllowedHostMatcher($allowedHosts);

        return new HttpServerConfig(
            trustedProxies: $trustedProxies,
            forwardedHeaderProfile: $profile,
            trustForwardedPrefix: $trustForwardedPrefix,
            allowedHosts: $allowedHosts,
        );
    }

    private function profile(mixed $value): ForwardedHeaderProfile
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException('HTTP server forwarded_header_profile must be a string.');
        }

        $profile = ForwardedHeaderProfile::tryFrom($value);
        if (!$profile instanceof ForwardedHeaderProfile) {
            throw new InvalidArgumentException(sprintf(
                'Invalid HTTP server forwarded_header_profile "%s". Expected one of: none, forwarded, x_forwarded.',
                $value,
            ));
        }

        return $profile;
    }

    /**
     * @return list<non-empty-string>
     */
    private function stringList(mixed $value, string $field): array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException(sprintf('HTTP server %s must be a list of strings.', $field));
        }

        $normalized = [];

        foreach ($value as $item) {
            if (!is_string($item) || trim($item) !== $item) {
                throw new InvalidArgumentException(sprintf(
                    'HTTP server %s must contain only non-empty strings.',
                    $field,
                ));
            }

            if ($item === '') {
                throw new InvalidArgumentException(sprintf(
                    'HTTP server %s must contain only non-empty strings.',
                    $field,
                ));
            }

            if (!in_array($item, $normalized, true)) {
                $normalized[] = $item;
            }
        }

        return $normalized;
    }
}
