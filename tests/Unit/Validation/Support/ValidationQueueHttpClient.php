<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Validation\Support;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class ValidationQueueHttpClient implements ClientInterface
{
    public ?RequestInterface $lastRequest = null;
    public int $requestCount = 0;
    public bool $throw = false;

    /** @var list<ResponseInterface> */
    private array $responses;

    public function __construct(ResponseInterface ...$responses)
    {
        $this->responses = array_values($responses);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->lastRequest = $request;
        $this->requestCount++;

        if ($this->throw) {
            throw new ValidationClientException('Network down');
        }

        return array_shift($this->responses) ?? new Response(200, [], '{"valid":true}');
    }
}

final class ValidationClientException extends \RuntimeException implements ClientExceptionInterface {}
