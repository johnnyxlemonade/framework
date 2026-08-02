<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule;

use Lemonade\Framework\Validation\Endpoint\ValidationEndpointProviderInterface;
use Lemonade\Framework\Validation\Rule\Traits\JsonFetchTrait;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class ValidIcoActiveRule implements ValidationRuleInterface
{
    use JsonFetchTrait;

    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly ValidationEndpointProviderInterface $endpointProvider,
    ) {}

    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($param, $data);

        if (!is_string($value)) {
            return false;
        }

        $ico = preg_replace('/\s+/', '', $value) ?? '';

        if ($ico === '' || preg_match('/^\d{8}$/', $ico) !== 1) {
            return false;
        }

        try {
            $url = $this->endpointProvider->activeCompanyValidationUrl($ico);
        } catch (\Throwable) {
            return false;
        }

        $json = $this->fetchJson($url);

        if ($json === null) {
            return false;
        }

        return ($json['valid'] ?? false) === true;
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
