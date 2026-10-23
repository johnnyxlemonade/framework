<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Request;

use InvalidArgumentException;

/**
 * Matches peer IP addresses against an explicit, prevalidated set of exact addresses and CIDR networks.
 */
final readonly class TrustedProxyMatcher
{
    /**
     * @var list<array{network: string, prefix: int, bytes: int}>
     */
    private array $networks;

    /**
     * Compiles trusted IPv4 and IPv6 entries without resolving hostnames or consulting network state.
     *
     * @param list<string> $trustedProxies
     *
     * @throws InvalidArgumentException When an entry is not an exact IP address or valid CIDR network.
     */
    public function __construct(array $trustedProxies = [])
    {
        $networks = [];

        foreach ($trustedProxies as $trustedProxy) {
            $networks[] = $this->parseNetwork($trustedProxy);
        }

        $this->networks = $networks;
    }

    /**
     * Reports whether the supplied literal IP address belongs to an explicitly trusted network.
     */
    public function isTrusted(string $ip): bool
    {
        $packedIp = @inet_pton(trim($ip));
        if ($packedIp === false) {
            return false;
        }

        foreach ($this->networks as $network) {
            if (strlen($packedIp) !== $network['bytes']) {
                continue;
            }

            if ($this->matchesPrefix($packedIp, $network['network'], $network['prefix'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{network: string, prefix: int, bytes: int}
     */
    private function parseNetwork(string $value): array
    {
        $entry = $value;
        if ($entry === '') {
            throw new InvalidArgumentException('Trusted proxy entries must not be empty.');
        }

        if (trim($entry) !== $entry) {
            throw new InvalidArgumentException('Trusted proxy entries must not contain surrounding whitespace.');
        }

        $parts = explode('/', $entry);
        if (count($parts) > 2 || $parts[0] === '') {
            throw new InvalidArgumentException(sprintf(
                'Trusted proxy "%s" must be an exact IP address or CIDR network.',
                $value,
            ));
        }

        $ip = $parts[0];
        $packed = @inet_pton($ip);
        if ($packed === false || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            throw new InvalidArgumentException(sprintf(
                'Trusted proxy "%s" contains an invalid IP address.',
                $value,
            ));
        }

        $maximumPrefix = strlen($packed) * 8;
        $prefix = $maximumPrefix;

        if (isset($parts[1])) {
            $rawPrefix = $parts[1];
            if (preg_match('/^(?:0|[1-9][0-9]*)$/', $rawPrefix) !== 1) {
                throw new InvalidArgumentException(sprintf(
                    'Trusted proxy "%s" contains an invalid CIDR prefix.',
                    $value,
                ));
            }

            $prefix = (int) $rawPrefix;
            if ($prefix > $maximumPrefix) {
                throw new InvalidArgumentException(sprintf(
                    'Trusted proxy "%s" uses a CIDR prefix outside the valid range 0-%d.',
                    $value,
                    $maximumPrefix,
                ));
            }
        }

        return [
            'network' => $packed,
            'prefix' => $prefix,
            'bytes' => strlen($packed),
        ];
    }

    private function matchesPrefix(string $ip, string $network, int $prefix): bool
    {
        $completeBytes = intdiv($prefix, 8);
        if ($completeBytes > 0 && substr($ip, 0, $completeBytes) !== substr($network, 0, $completeBytes)) {
            return false;
        }

        $remainingBits = $prefix % 8;
        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xff << (8 - $remainingBits)) & 0xff;

        return (ord($ip[$completeBytes]) & $mask) === (ord($network[$completeBytes]) & $mask);
    }
}
