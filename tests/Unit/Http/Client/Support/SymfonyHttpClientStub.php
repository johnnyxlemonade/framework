<?php

declare(strict_types=1);

namespace Symfony\Contracts\HttpClient {
    interface HttpClientInterface
    {
    }
}

namespace Symfony\Component\HttpClient {
    use LogicException;
    use Psr\Http\Client\ClientInterface;
    use Psr\Http\Message\RequestInterface;
    use Psr\Http\Message\ResponseInterface;
    use Symfony\Contracts\HttpClient\HttpClientInterface;

    final readonly class StubHttpClient implements HttpClientInterface
    {
        /** @param array<string, mixed> $options */
        public function __construct(public array $options)
        {
        }
    }

    final readonly class HttpClient
    {
        /** @param array<string, mixed> $options */
        public static function create(array $options): HttpClientInterface
        {
            return new StubHttpClient($options);
        }
    }

    final readonly class Psr18Client implements ClientInterface
    {
        public function __construct(public HttpClientInterface $nativeClient)
        {
        }

        public function sendRequest(RequestInterface $request): ResponseInterface
        {
            throw new LogicException('The Symfony HTTP client test stub does not send requests.');
        }
    }
}
