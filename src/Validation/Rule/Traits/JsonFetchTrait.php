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
        $services = $this->httpServices(requireStreamFactory: false);
        if ($services === null) {
            return null;
        }

        try {
            $request = $services['requestFactory']
                ->createRequest('GET', $url)
                ->withHeader('User-Agent', 'Lemonade/Validation')
                ->withHeader('Accept', 'application/json');

            $response = $services['client']->sendRequest($request);

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
        $services = $this->httpServices(requireStreamFactory: true);
        if ($services === null || $services['streamFactory'] === null) {
            return null;
        }

        try {
            $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            $request = $services['requestFactory']
                ->createRequest('POST', $url)
                ->withHeader('User-Agent', 'Lemonade/Validation')
                ->withHeader('Accept', 'application/json')
                ->withHeader('Content-Type', 'application/json')
                ->withBody($services['streamFactory']->createStream($body));

            $response = $services['client']->sendRequest($request);

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
        $services = $this->httpServices(requireStreamFactory: true);
        if ($services === null || $services['streamFactory'] === null) {
            return null;
        }

        try {
            $request = $services['requestFactory']
                ->createRequest('POST', $url)
                ->withHeader('User-Agent', 'Lemonade/Validation')
                ->withHeader('Accept', 'application/json')
                ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
                ->withBody($services['streamFactory']->createStream(http_build_query($payload)));

            $response = $services['client']->sendRequest($request);

            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                return null;
            }

            return $this->decodeJson((string) $response->getBody());
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{
     *     client: ClientInterface,
     *     requestFactory: RequestFactoryInterface,
     *     streamFactory: StreamFactoryInterface|null
     * }|null
     */
    private function httpServices(bool $requireStreamFactory): ?array
    {
        try {
            $client = service(ClientInterface::class);
            $requestFactory = service(RequestFactoryInterface::class);
            $streamFactory = $requireStreamFactory ? service(StreamFactoryInterface::class) : null;
        } catch (\Throwable) {
            return null;
        }

        if (!$client instanceof ClientInterface || !$requestFactory instanceof RequestFactoryInterface) {
            return null;
        }

        if ($requireStreamFactory && !$streamFactory instanceof StreamFactoryInterface) {
            return null;
        }

        return [
            'client' => $client,
            'requestFactory' => $requestFactory,
            'streamFactory' => $streamFactory instanceof StreamFactoryInterface ? $streamFactory : null,
        ];
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
}
