<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Http;

use Lemonade\Framework\Http\Request\HttpMethod;
use Lemonade\Framework\Http\Request\HttpRequestInspector;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

final class RequestData
{
    private bool $jsonDecoded = false;
    /**
     * @var array<string, mixed>|null
     */
    private ?array $jsonPayloadCache = null;

    public function __construct(
        private readonly ServerRequestInterface $request,
        private readonly HttpRequestInspector $inspector = new HttpRequestInspector(),
    ) {}

    public function request(): ServerRequestInterface
    {
        return $this->request;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $parsed = $this->request->getParsedBody();
        if (is_array($parsed) && array_key_exists($key, $parsed)) {
            return $parsed[$key];
        }

        $query = $this->request->getQueryParams();
        if (array_key_exists($key, $query)) {
            return $query[$key];
        }

        $payload = $this->jsonPayload();
        if (array_key_exists($key, $payload)) {
            return $payload[$key];
        }

        return $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        $query = $this->request->getQueryParams();

        return array_key_exists($key, $query) ? $query[$key] : $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function queryAll(): array
    {
        return $this->normalizeAssoc($this->request->getQueryParams());
    }

    public function post(string $key, mixed $default = null): mixed
    {
        $parsed = $this->request->getParsedBody();

        if (!is_array($parsed)) {
            return $default;
        }

        return array_key_exists($key, $parsed) ? $parsed[$key] : $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function postAll(): array
    {
        $parsed = $this->request->getParsedBody();

        return is_array($parsed) ? $this->normalizeAssoc($parsed) : [];
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $value = $this->request->getHeaderLine($name);

        return $value !== '' ? $value : $default;
    }

    /**
     * @return array<string, string[]>
     */
    public function headers(): array
    {
        $headers = [];

        foreach ($this->request->getHeaders() as $key => $values) {
            if (!is_string($key)) {
                continue;
            }

            $headers[$key] = $values;
        }

        return $headers;
    }

    public function cookie(string $name, mixed $default = null): mixed
    {
        $cookies = $this->request->getCookieParams();

        return array_key_exists($name, $cookies) ? $cookies[$name] : $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function cookies(): array
    {
        return $this->normalizeAssoc($this->request->getCookieParams());
    }

    public function server(string $key, mixed $default = null): mixed
    {
        $server = $this->request->getServerParams();

        return array_key_exists($key, $server) ? $server[$key] : $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function serverAll(): array
    {
        return $this->normalizeAssoc($this->request->getServerParams());
    }

    public function body(): string
    {
        return (string) $this->request->getBody();
    }

    public function jsonInput(string $key, mixed $default = null): mixed
    {
        $payload = $this->jsonPayload();

        return array_key_exists($key, $payload) ? $payload[$key] : $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonPayload(): array
    {
        if ($this->jsonDecoded) {
            return is_array($this->jsonPayloadCache) ? $this->jsonPayloadCache : [];
        }

        $this->jsonDecoded = true;

        $decoded = json_decode($this->body(), true);
        $this->jsonPayloadCache = is_array($decoded) ? $this->normalizeAssoc($decoded) : [];

        return $this->jsonPayloadCache;
    }

    public function isJsonRequest(): bool
    {
        $contentType = strtolower($this->request->getHeaderLine('Content-Type'));

        return str_contains($contentType, 'application/json');
    }

    public function acceptsJson(): bool
    {
        $accept = strtolower($this->request->getHeaderLine('Accept'));

        return $accept !== '' && (str_contains($accept, 'application/json') || str_contains($accept, 'application/*') || str_contains($accept, '*/*'));
    }

    public function expectsJson(): bool
    {
        return $this->isJsonRequest() || $this->acceptsJson() || $this->isAjaxRequest();
    }

    /**
     * @return UploadedFileInterface|array<string, mixed>|null
     */
    public function file(string $name): UploadedFileInterface|array|null
    {
        $files = $this->request->getUploadedFiles();

        $file = $files[$name] ?? null;

        if ($file instanceof UploadedFileInterface) {
            return $file;
        }

        return is_array($file) ? $this->normalizeAssoc($file) : null;
    }

    /**
     * @return array<string, UploadedFileInterface|array<string, mixed>>
     */
    public function files(): array
    {
        $files = [];

        foreach ($this->request->getUploadedFiles() as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            if ($value instanceof UploadedFileInterface) {
                $files[$key] = $value;
                continue;
            }

            if (is_array($value)) {
                $files[$key] = $this->normalizeAssoc($value);
            }
        }

        return $files;
    }

    public function isAjaxRequest(): bool
    {
        return strtolower($this->request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest';
    }

    public function ip(): ?string
    {
        return $this->inspector->clientIp($this->request);
    }

    public function userAgent(?string $default = null): ?string
    {
        $value = $this->inspector->userAgent($this->request);

        return $value !== '' ? $value : $default;
    }

    public function referer(?string $default = null): ?string
    {
        $value = $this->inspector->referer($this->request);

        return $value !== '' ? $value : $default;
    }

    public function method(): string
    {
        return strtoupper($this->request->getMethod());
    }

    public function isMethod(HttpMethod|string $method): bool
    {
        $value = $method instanceof HttpMethod ? $method->value : strtoupper($method);

        return $this->method() === $value;
    }

    public function isGet(): bool
    {
        return $this->isMethod(HttpMethod::GET);
    }

    public function isPost(): bool
    {
        return $this->isMethod(HttpMethod::POST);
    }

    public function isPut(): bool
    {
        return $this->isMethod(HttpMethod::PUT);
    }

    public function isPatch(): bool
    {
        return $this->isMethod(HttpMethod::PATCH);
    }

    public function isDelete(): bool
    {
        return $this->isMethod(HttpMethod::DELETE);
    }

    public function isHead(): bool
    {
        return $this->isMethod(HttpMethod::HEAD);
    }

    public function isOptions(): bool
    {
        return $this->isMethod(HttpMethod::OPTIONS);
    }

    public function inputString(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    public function inputInt(string $key, int $default = 0): int
    {
        $value = $this->input($key);

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        return $default;
    }

    public function inputFloat(string $key, float $default = 0.0): float
    {
        $value = $this->input($key);

        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return $default;
    }

    public function inputBool(string $key, bool $default = false): bool
    {
        $value = $this->input($key);

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value !== 0;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        return $default;
    }

    /**
     * @param array<mixed> $values
     * @return array<string, mixed>
     */
    private function normalizeAssoc(array $values): array
    {
        $normalized = [];

        foreach ($values as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }
}
