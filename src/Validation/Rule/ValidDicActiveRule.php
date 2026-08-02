<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule;

use Lemonade\Framework\Validation\Endpoint\ValidationEndpointNotConfiguredException;
use Lemonade\Framework\Validation\Endpoint\VatValidationEndpointProviderInterface;
use Lemonade\Framework\Validation\Rule\Traits\JsonFetchTrait;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class ValidDicActiveRule implements ValidationRuleInterface
{
    use JsonFetchTrait;

    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly VatValidationEndpointProviderInterface $endpointProvider,
    ) {}

    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($param, $data);

        if (!is_string($value)) {
            return false;
        }

        $vatId = strtoupper((string) preg_replace('/\s+/', '', $value));

        if (strlen($vatId) < 4) {
            return false;
        }

        $country = substr($vatId, 0, 2);
        $number = substr($vatId, 2);

        if (preg_match('/^[A-Z]{2}$/', $country) !== 1 || $number === '') {
            return false;
        }

        try {
            $url = $this->endpointProvider->validationUrl($country, $number);
        } catch (ValidationEndpointNotConfiguredException) {
            return false;
        }

        $json = $this->fetchJson($url);

        if ($json === null) {
            return false;
        }

        return ($json['isValid'] ?? false) === true;
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
