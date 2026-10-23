<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule;

use Lemonade\Framework\Http\Request\ResolvedRequestContext;
use Lemonade\Framework\Validation\Endpoint\RecaptchaEndpointProviderInterface;
use Lemonade\Framework\Validation\Endpoint\ValidationEndpointNotConfiguredException;
use Lemonade\Framework\Validation\Rule\Traits\JsonFetchTrait;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Verifies one reCAPTCHA token and includes only the trust-resolved client address.
 */
final class RecaptchaRule implements ValidationRuleInterface
{
    use JsonFetchTrait;

    /**
     * Binds remote verification to HTTP transport services and an optional active request context.
     */
    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly RecaptchaEndpointProviderInterface $endpointProvider,
        private readonly ?ResolvedRequestContext $requestContext = null,
    ) {
    }

    /**
     * Returns true only when the configured verification endpoint accepts the supplied token.
     */
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

        $remoteIp = $this->requestContext?->clientIp();
        if ($remoteIp !== null) {
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
