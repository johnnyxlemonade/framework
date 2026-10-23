<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Request;

use Lemonade\Framework\Http\Config\ForwardedHeaderProfile;
use Lemonade\Framework\Http\Config\HttpServerConfig;
use Lemonade\Framework\Http\Request\Exception\InvalidRequestMetadataException;
use Lemonade\Framework\Http\Request\Internal\AllowedHostMatcher;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Resolves client identity and external request origin across one explicit proxy trust boundary.
 */
final readonly class RequestContextResolver
{
    private const int MAX_FORWARDED_HEADER_LENGTH = 8192;
    private const int MAX_PROXY_HOPS = 32;
    private const int MAX_PREFIX_LENGTH = 1024;

    private AllowedHostMatcher $allowedHosts;

    /**
     * Binds request metadata resolution to validated server trust configuration.
     */
    public function __construct(
        private HttpServerConfig $config,
        private TrustedProxyMatcher $trustedProxies,
    ) {
        $this->allowedHosts = new AllowedHostMatcher($config->allowedHosts());
    }

    /**
     * Produces a fail-closed context without consulting global server state.
     *
     * @throws InvalidRequestMetadataException When security-relevant request metadata is malformed or ambiguous.
     */
    public function resolve(ServerRequestInterface $request): ResolvedRequestContext
    {
        $peerIp = $this->peerIp($request);
        $clientIp = $peerIp;
        $scheme = $this->directScheme($request);
        [$host, $port] = $this->directAuthority($request);
        $prefix = '';
        $viaTrustedProxy = $peerIp !== null && $this->trustedProxies->isTrusted($peerIp);

        if ($viaTrustedProxy && $this->config->forwardedHeaderProfile() !== ForwardedHeaderProfile::None) {
            $this->assertUnambiguousHeaderFamily($request);

            $forwarded = match ($this->config->forwardedHeaderProfile()) {
                ForwardedHeaderProfile::Forwarded => $this->resolveForwarded($request, $peerIp),
                ForwardedHeaderProfile::XForwarded => $this->resolveXForwarded($request, $peerIp),
                ForwardedHeaderProfile::None => null,
            };

            if ($forwarded !== null) {
                $clientIp = $forwarded['clientIp'];
                $originProvided = $forwarded['scheme'] !== null
                    || $forwarded['host'] !== null
                    || $forwarded['authorityPort'] !== null
                    || $forwarded['port'] !== null;

                if ($originProvided) {
                    $scheme = $forwarded['scheme'] ?? $scheme;
                    $host = $forwarded['host'] ?? $host;
                    $port = $this->mergePorts($forwarded['authorityPort'], $forwarded['port']);
                }

                $prefix = $forwarded['prefix'] ?? '';
            }
        }

        $port = $this->normalizePortForScheme($port, $scheme);
        if ($host === null || !$this->allowedHosts->allows($host)) {
            $host = null;
            $port = null;
        }

        return new ResolvedRequestContext(
            peerIp: $peerIp,
            clientIp: $clientIp,
            viaTrustedProxy: $viaTrustedProxy,
            scheme: $scheme,
            host: $host,
            port: $port,
            prefix: $prefix,
        );
    }

    private function peerIp(ServerRequestInterface $request): ?string
    {
        $value = $request->getServerParams()['REMOTE_ADDR'] ?? null;
        if ($value === null || $value === '') {
            return null;
        }

        if (
            !is_string($value)
            || trim($value) !== $value
            || filter_var($value, FILTER_VALIDATE_IP) === false
        ) {
            throw new InvalidRequestMetadataException('REMOTE_ADDR must be a valid literal IP address.');
        }

        return $value;
    }

    /**
     * @return 'http'|'https'
     */
    private function directScheme(ServerRequestInterface $request): string
    {
        $uriScheme = strtolower($request->getUri()->getScheme());
        if ($uriScheme !== '') {
            if ($uriScheme !== 'http' && $uriScheme !== 'https') {
                throw new InvalidRequestMetadataException(sprintf(
                    'Request URI scheme "%s" is not supported.',
                    $uriScheme,
                ));
            }

            return $uriScheme;
        }

        $serverParams = $request->getServerParams();
        $https = $this->serverString($serverParams['HTTPS'] ?? null, 'HTTPS');
        $normalizedHttps = strtolower(trim($https ?? ''));

        if (in_array($normalizedHttps, ['on', '1', 'true', 'https'], true)) {
            return 'https';
        }

        if (!in_array($normalizedHttps, ['', 'off', '0', 'false'], true)) {
            throw new InvalidRequestMetadataException('HTTPS server metadata contains an unsupported value.');
        }

        $serverPort = $this->optionalPort($serverParams['SERVER_PORT'] ?? null, 'SERVER_PORT');

        return $serverPort === 443 ? 'https' : 'http';
    }

    /**
     * @return array{0: string|null, 1: int|null}
     */
    private function directAuthority(ServerRequestInterface $request): array
    {
        $uriHost = $request->getUri()->getHost();
        $uriAuthority = $uriHost !== ''
            ? [$this->validatedHost($uriHost), $request->getUri()->getPort()]
            : [null, null];
        $hostHeaders = $request->getHeader('Host');
        $headerAuthority = [null, null];

        if ($hostHeaders !== []) {
            $authorities = [];

            foreach ($hostHeaders as $hostHeader) {
                if (str_contains($hostHeader, ',')) {
                    throw new InvalidRequestMetadataException('Host header must contain exactly one authority value.');
                }

                $authorities[] = $this->parseAuthority($hostHeader, 'Host');
            }

            $headerAuthority = $authorities[0];

            foreach ($authorities as $authority) {
                if ($authority !== $headerAuthority) {
                    throw new InvalidRequestMetadataException(
                        'Repeated Host header values must describe the same authority.',
                    );
                }
            }
        }

        if (
            $uriAuthority[0] !== null
            && $headerAuthority[0] !== null
            && $uriAuthority !== $headerAuthority
        ) {
            throw new InvalidRequestMetadataException('Request URI authority and Host header are inconsistent.');
        }

        $host = $headerAuthority[0] ?? $uriAuthority[0];
        $port = $headerAuthority[1] ?? $uriAuthority[1];

        if ($port === null) {
            $port = $this->optionalPort($request->getServerParams()['SERVER_PORT'] ?? null, 'SERVER_PORT');
        }

        return [$host, $port];
    }

    private function assertUnambiguousHeaderFamily(ServerRequestInterface $request): void
    {
        if (!$request->hasHeader('Forwarded')) {
            return;
        }

        $xForwardedHeaders = [
            'X-Forwarded-For',
            'X-Forwarded-Proto',
            'X-Forwarded-Host',
            'X-Forwarded-Port',
        ];

        if ($this->config->forwardedHeaderProfile() === ForwardedHeaderProfile::XForwarded) {
            $xForwardedHeaders[] = 'X-Forwarded-Prefix';
        }

        foreach ($xForwardedHeaders as $header) {
            if ($request->hasHeader($header)) {
                throw new InvalidRequestMetadataException(
                    'Forwarded and X-Forwarded metadata must not be combined in one request.',
                );
            }
        }
    }

    /**
     * @return array{
     *     clientIp: string,
     *     scheme: 'http'|'https'|null,
     *     host: string|null,
     *     authorityPort: int|null,
     *     port: int|null,
     *     prefix: string|null
     * }|null
     */
    private function resolveForwarded(ServerRequestInterface $request, string $peerIp): ?array
    {
        $value = $request->getHeaderLine('Forwarded');
        if ($value === '') {
            return null;
        }

        $this->assertHeaderLength($value, 'Forwarded');
        $elements = $this->splitOutsideQuotes($value, ',');
        $this->assertHopCount($elements);
        $parsed = [];

        foreach ($elements as $element) {
            $parameters = [];

            foreach ($this->splitOutsideQuotes($element, ';') as $parameter) {
                $pair = explode('=', $parameter, 2);
                if (count($pair) !== 2 || preg_match('/^[a-z][a-z0-9_-]*$/i', trim($pair[0])) !== 1) {
                    throw new InvalidRequestMetadataException('Forwarded header contains a malformed parameter.');
                }

                $name = strtolower(trim($pair[0]));
                if (array_key_exists($name, $parameters)) {
                    throw new InvalidRequestMetadataException(sprintf(
                        'Forwarded element contains duplicate "%s" parameters.',
                        $name,
                    ));
                }

                $parameters[$name] = $this->headerValue($pair[1], 'Forwarded');
            }

            if (!isset($parameters['for'])) {
                throw new InvalidRequestMetadataException('Every Forwarded element must contain a for parameter.');
            }

            $parsed[] = [
                'ip' => $this->nodeIp($parameters['for'], 'Forwarded for'),
                'proto' => $parameters['proto'] ?? null,
                'host' => $parameters['host'] ?? null,
            ];
        }

        $boundary = $this->trustBoundary(
            array_map(static fn(array $element): string => $element['ip'], $parsed),
            $peerIp,
        );
        $selected = $parsed[$boundary['index']];
        [$host, $authorityPort] = $selected['host'] !== null
            ? $this->parseAuthority($selected['host'], 'Forwarded host')
            : [null, null];
        $prefix = $this->trustedPrefix(
            $request,
            count($parsed),
            $boundary['index'],
        );

        return [
            'clientIp' => $boundary['clientIp'],
            'scheme' => $selected['proto'] !== null ? $this->scheme($selected['proto'], 'Forwarded proto') : null,
            'host' => $host,
            'authorityPort' => $authorityPort,
            'port' => null,
            'prefix' => $prefix,
        ];
    }

    /**
     * @return array{
     *     clientIp: string,
     *     scheme: 'http'|'https'|null,
     *     host: string|null,
     *     authorityPort: int|null,
     *     port: int|null,
     *     prefix: string|null
     * }
     */
    private function resolveXForwarded(ServerRequestInterface $request, string $peerIp): array
    {
        $forValue = $request->getHeaderLine('X-Forwarded-For');
        $forwardedIps = [];

        if ($forValue !== '') {
            $this->assertHeaderLength($forValue, 'X-Forwarded-For');
            $parts = $this->splitOutsideQuotes($forValue, ',');
            $this->assertHopCount($parts);

            foreach ($parts as $part) {
                $forwardedIps[] = $this->nodeIp($this->headerValue($part, 'X-Forwarded-For'), 'X-Forwarded-For');
            }
        }

        $boundary = $this->trustBoundary($forwardedIps, $peerIp);
        $expectedValues = count($forwardedIps);
        $selectedIndex = $boundary['index'];
        $proto = $this->alignedHeaderValue(
            $request,
            'X-Forwarded-Proto',
            $expectedValues,
            $selectedIndex,
        );
        $hostValue = $this->alignedHeaderValue(
            $request,
            'X-Forwarded-Host',
            $expectedValues,
            $selectedIndex,
        );
        $portValue = $this->alignedHeaderValue(
            $request,
            'X-Forwarded-Port',
            $expectedValues,
            $selectedIndex,
        );
        [$host, $authorityPort] = $hostValue !== null
            ? $this->parseAuthority($hostValue, 'X-Forwarded-Host')
            : [null, null];

        return [
            'clientIp' => $boundary['clientIp'],
            'scheme' => $proto !== null ? $this->scheme($proto, 'X-Forwarded-Proto') : null,
            'host' => $host,
            'authorityPort' => $authorityPort,
            'port' => $portValue !== null ? $this->port($portValue, 'X-Forwarded-Port') : null,
            'prefix' => $this->trustedPrefix($request, $expectedValues, $selectedIndex),
        ];
    }

    /**
     * @param list<string> $forwardedIps
     * @return array{clientIp: string, index: int}
     */
    private function trustBoundary(array $forwardedIps, string $peerIp): array
    {
        if ($forwardedIps === []) {
            return ['clientIp' => $peerIp, 'index' => 0];
        }

        $chain = [...$forwardedIps, $peerIp];
        $index = count($chain) - 1;

        while ($index > 0 && $this->trustedProxies->isTrusted($chain[$index])) {
            --$index;
        }

        return [
            'clientIp' => $chain[$index],
            'index' => min($index, count($forwardedIps) - 1),
        ];
    }

    private function alignedHeaderValue(
        ServerRequestInterface $request,
        string $header,
        int $expectedValues,
        int $selectedIndex,
    ): ?string {
        $value = $request->getHeaderLine($header);
        if ($value === '') {
            return null;
        }

        $this->assertHeaderLength($value, $header);
        $values = $this->splitOutsideQuotes($value, ',');

        if (count($values) === 1) {
            return $this->headerValue($values[0], $header);
        }

        if ($expectedValues === 0 || count($values) !== $expectedValues) {
            throw new InvalidRequestMetadataException(sprintf(
                '%s must contain one authoritative value or %d values aligned with the proxy chain.',
                $header,
                max(1, $expectedValues),
            ));
        }

        return $this->headerValue($values[$selectedIndex], $header);
    }

    private function trustedPrefix(
        ServerRequestInterface $request,
        int $expectedValues,
        int $selectedIndex,
    ): ?string {
        if (!$this->config->trustsForwardedPrefix()) {
            return null;
        }

        $prefix = $this->alignedHeaderValue(
            $request,
            'X-Forwarded-Prefix',
            $expectedValues,
            $selectedIndex,
        );

        return $prefix !== null ? $this->prefix($prefix) : null;
    }

    /**
     * @return list<non-empty-string>
     */
    private function splitOutsideQuotes(string $value, string $delimiter): array
    {
        $parts = [];
        $part = '';
        $quoted = false;
        $escaped = false;

        for ($index = 0, $length = strlen($value); $index < $length; ++$index) {
            $character = $value[$index];

            if ($escaped) {
                $part .= $character;
                $escaped = false;
                continue;
            }

            if ($quoted && $character === '\\') {
                $part .= $character;
                $escaped = true;
                continue;
            }

            if ($character === '"') {
                $quoted = !$quoted;
                $part .= $character;
                continue;
            }

            if (!$quoted && $character === $delimiter) {
                $parts[] = $this->nonEmptyHeaderPart($part);
                $part = '';
                continue;
            }

            $part .= $character;
        }

        if ($quoted || $escaped) {
            throw new InvalidRequestMetadataException('Forwarding metadata contains an unterminated quoted value.');
        }

        $parts[] = $this->nonEmptyHeaderPart($part);

        return $parts;
    }

    /**
     * @return non-empty-string
     */
    private function nonEmptyHeaderPart(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new InvalidRequestMetadataException('Forwarding metadata contains an empty list element.');
        }

        return $value;
    }

    private function headerValue(string $value, string $header): string
    {
        $value = trim($value);
        if (str_starts_with($value, '"')) {
            if (!str_ends_with($value, '"') || strlen($value) < 2) {
                throw new InvalidRequestMetadataException(sprintf('%s contains malformed quoting.', $header));
            }

            $value = substr($value, 1, -1);
            $value = preg_replace('/\\\\(.)/s', '$1', $value) ?? '';
        } elseif (str_contains($value, '"')) {
            throw new InvalidRequestMetadataException(sprintf('%s contains malformed quoting.', $header));
        }

        if ($value === '' || preg_match('/[\x00-\x1f\x7f]/', $value) === 1) {
            throw new InvalidRequestMetadataException(sprintf('%s contains an empty or unsafe value.', $header));
        }

        return $value;
    }

    private function nodeIp(string $value, string $source): string
    {
        $normalized = strtolower($value);
        if ($normalized === 'unknown' || str_starts_with($normalized, '_')) {
            throw new InvalidRequestMetadataException(sprintf('%s contains an unsupported node identifier.', $source));
        }

        if (preg_match('/^\[([^\]]+)](?::([0-9]+))?$/', $value, $matches) === 1) {
            $this->optionalPort($matches[2] ?? null, $source);

            if (filter_var($matches[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
                throw new InvalidRequestMetadataException(sprintf('%s contains an invalid IPv6 address.', $source));
            }

            return $matches[1];
        }

        if (filter_var($value, FILTER_VALIDATE_IP) !== false) {
            return $value;
        }

        if (preg_match('/^([^:]+):([0-9]+)$/', $value, $matches) === 1) {
            $this->port($matches[2], $source);

            if (filter_var($matches[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                return $matches[1];
            }
        }

        throw new InvalidRequestMetadataException(sprintf('%s must contain a literal IP address.', $source));
    }

    /**
     * @return array{0: string, 1: int|null}
     */
    private function parseAuthority(string $value, string $source): array
    {
        $authority = $this->headerValue($value, $source);

        if (preg_match('/^\[([^\]]+)](?::([^:]+))?$/', $authority, $matches) === 1) {
            $host = $this->validatedHost($matches[1]);
            if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
                throw new InvalidRequestMetadataException(sprintf('%s contains an invalid IPv6 host.', $source));
            }

            return [$host, $this->optionalPort($matches[2] ?? null, $source)];
        }

        if (substr_count($authority, ':') > 1) {
            throw new InvalidRequestMetadataException(sprintf(
                '%s must enclose an IPv6 host in brackets.',
                $source,
            ));
        }

        $parts = explode(':', $authority, 2);
        $host = $this->validatedHost($parts[0]);
        $port = isset($parts[1]) ? $this->port($parts[1], $source) : null;

        return [$host, $port];
    }

    private function validatedHost(string $host): string
    {
        $host = $this->allowedHosts->normalizeHost($host);
        if (!$this->allowedHosts->isValidHost($host)) {
            throw new InvalidRequestMetadataException(sprintf('Request host "%s" is malformed.', $host));
        }

        return $host;
    }

    /**
     * @return 'http'|'https'
     */
    private function scheme(string $value, string $source): string
    {
        $scheme = strtolower(trim($value));
        if ($scheme !== 'http' && $scheme !== 'https') {
            throw new InvalidRequestMetadataException(sprintf('%s must be http or https.', $source));
        }

        return $scheme;
    }

    private function optionalPort(mixed $value, string $source): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_int($value) && !is_string($value)) {
            throw new InvalidRequestMetadataException(sprintf('%s contains a malformed port.', $source));
        }

        return $this->port((string) $value, $source);
    }

    private function port(string $value, string $source): int
    {
        if (preg_match('/^(?:[1-9][0-9]{0,4})$/', $value) !== 1) {
            throw new InvalidRequestMetadataException(sprintf('%s contains a malformed port.', $source));
        }

        $port = (int) $value;
        if ($port > 65535) {
            throw new InvalidRequestMetadataException(sprintf('%s contains a port outside the valid range.', $source));
        }

        return $port;
    }

    private function mergePorts(?int $authorityPort, ?int $forwardedPort): ?int
    {
        if ($authorityPort !== null && $forwardedPort !== null && $authorityPort !== $forwardedPort) {
            throw new InvalidRequestMetadataException(
                'Forwarded host port and X-Forwarded-Port describe different external ports.',
            );
        }

        return $forwardedPort ?? $authorityPort;
    }

    private function normalizePortForScheme(?int $port, string $scheme): ?int
    {
        if (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)) {
            return null;
        }

        return $port;
    }

    private function prefix(string $value): string
    {
        if (
            strlen($value) > self::MAX_PREFIX_LENGTH
            || !str_starts_with($value, '/')
            || str_contains($value, '\\')
            || str_contains($value, '//')
            || str_contains($value, '?')
            || str_contains($value, '#')
            || preg_match('/[\x00-\x1f\x7f]/', $value) === 1
            || preg_match('/%(?:0[0-9a-f]|1[0-9a-f]|23|2e|2f|3f|5c|7f)/i', $value) === 1
        ) {
            throw new InvalidRequestMetadataException('X-Forwarded-Prefix contains an unsafe path.');
        }

        foreach (explode('/', $value) as $segment) {
            if ($segment === '.' || $segment === '..') {
                throw new InvalidRequestMetadataException('X-Forwarded-Prefix must not contain traversal segments.');
            }
        }

        $normalized = rtrim($value, '/');

        return $normalized === '/' ? '' : $normalized;
    }

    private function assertHeaderLength(string $value, string $header): void
    {
        if (strlen($value) > self::MAX_FORWARDED_HEADER_LENGTH) {
            throw new InvalidRequestMetadataException(sprintf('%s exceeds the supported length.', $header));
        }
    }

    /**
     * @param list<string> $hops
     */
    private function assertHopCount(array $hops): void
    {
        if (count($hops) > self::MAX_PROXY_HOPS) {
            throw new InvalidRequestMetadataException(sprintf(
                'Forwarding metadata exceeds the supported limit of %d proxy hops.',
                self::MAX_PROXY_HOPS,
            ));
        }
    }

    private function serverString(mixed $value, string $field): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new InvalidRequestMetadataException(sprintf('%s server metadata must be a string.', $field));
        }

        return $value;
    }
}
