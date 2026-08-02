<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule;

use Lemonade\Framework\Validation\Endpoint\RecaptchaEndpointProviderInterface;
use Lemonade\Framework\Validation\Endpoint\ValidationEndpointNotConfiguredException;
use Lemonade\Framework\Validation\Rule\Traits\JsonFetchTrait;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class RecaptchaRule implements ValidationRuleInterface
{
    use JsonFetchTrait;

    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly RecaptchaEndpointProviderInterface $endpointProvider,
    ) {}

    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($data);

        $secret = trim($param ?? '');
        $token = is_string($value) ? trim($value) : '';

        if ($secret === '' || $token === '') {
            return false;
        }

        $payload = [
            'secret' => $secret,
            'response' => $token,
        ];

        $remoteIpRaw = $_SERVER['REMOTE_ADDR'] ?? '';
        $remoteIp = is_scalar($remoteIpRaw) ? trim((string) $remoteIpRaw) : '';
        if ($remoteIp !== '') {
            $payload['remoteip'] = $remoteIp;
        }

        try {
            $url = $this->endpointProvider->verificationUrl();
        } catch (ValidationEndpointNotConfiguredException) {
            return false;
        }

        $json = $this->postForm($url, $payload);

        if ($json === null) {
            return false;
        }

        return ($json['success'] ?? false) === true;
    }

    protected function jsonFetchClient(): ClientInterface
    {
        return $this->client;
    }

    protected function jsonFetchRequestFactory(): RequestFactoryInterface
    {
        return $this->requestFactory;
    }

    protected function jsonFetchStreamFactory(): StreamFactoryInterface
    {
        return $this->streamFactory;
    }
}
