<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule\Traits;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

trait JsonFetchTrait
{
    /**
     * @return array<string, mixed>|null
     */
    protected function fetchJson(string $url): ?array
    {
        try {
            $request = $this->jsonFetchRequestFactory()
                ->createRequest('GET', $url)
                ->withHeader('User-Agent', 'Lemonade/Validation')
                ->withHeader('Accept', 'application/json');

            $response = $this->jsonFetchClient()->sendRequest($request);

            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                return null;
            }

            return $this->decodeJson((string) $response->getBody());
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, scalar|null> $payload
     * @return array<string, mixed>|null
     */
    protected function postJson(string $url, array $payload): ?array
    {
        try {
            $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            $request = $this->jsonFetchRequestFactory()
                ->createRequest('POST', $url)
                ->withHeader('User-Agent', 'Lemonade/Validation')
                ->withHeader('Accept', 'application/json')
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->jsonFetchStreamFactory()->createStream($body));

            $response = $this->jsonFetchClient()->sendRequest($request);

            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                return null;
            }

            return $this->decodeJson((string) $response->getBody());
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, scalar|null> $payload
     * @return array<string, mixed>|null
     */
    protected function postForm(string $url, array $payload): ?array
    {
        try {
            $request = $this->jsonFetchRequestFactory()
                ->createRequest('POST', $url)
                ->withHeader('User-Agent', 'Lemonade/Validation')
                ->withHeader('Accept', 'application/json')
                ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
                ->withBody($this->jsonFetchStreamFactory()->createStream(http_build_query($payload)));

            $response = $this->jsonFetchClient()->sendRequest($request);

            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                return null;
            }

            return $this->decodeJson((string) $response->getBody());
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJson(string $body): ?array
    {
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($decoded)) {
            return null;
        }

        $normalized = [];
        foreach ($decoded as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    abstract protected function jsonFetchClient(): ClientInterface;

    abstract protected function jsonFetchRequestFactory(): RequestFactoryInterface;

    abstract protected function jsonFetchStreamFactory(): StreamFactoryInterface;
}
