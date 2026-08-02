<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule;

use Lemonade\Framework\Validation\Endpoint\ValidationEndpointProviderInterface;
use Lemonade\Framework\Validation\Rule\Traits\JsonFetchTrait;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class ValidEmailHeavyRule implements ValidationRuleInterface, ValidationRuleFailureDetailsInterface
{
    use JsonFetchTrait;

    /**
     * @var array<string, string>
     */
    private const FAILURE_TRANSLATION_MAP = [
        'missing' => 'missing',
        'error' => 'syntax',
        'blacklist' => 'blacklist',
        'spam' => 'spam',
        'checkdnsrr' => 'dns',
    ];

    private ?string $failureTranslate = null;

    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly ValidationEndpointProviderInterface $endpointProvider,
    ) {}

    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($param, $data);

        $this->failureTranslate = null;

        if (!is_string($value) || trim($value) === '') {
            $this->failureTranslate = 'missing';

            return false;
        }

        $email = trim($value);
        try {
            $url = $this->endpointProvider->emailValidationUrl($email);
        } catch (\Throwable) {
            $this->failureTranslate = 'unavailable';

            return false;
        }

        $json = $this->fetchJson($url);

        if ($json === null) {
            $this->failureTranslate = 'unavailable';

            return false;
        }

        if (($json['valid'] ?? false) === true) {
            return true;
        }

        $apiTranslate = $json['translate'] ?? null;

        if (is_string($apiTranslate) && trim($apiTranslate) !== '') {
            $apiTranslate = trim($apiTranslate);
            $this->failureTranslate = self::FAILURE_TRANSLATION_MAP[$apiTranslate] ?? 'invalid';

            return false;
        }

        $this->failureTranslate = 'invalid';

        return false;
    }

    public function pullFailureMessage(): ?string
    {
        return null;
    }

    public function pullFailureTranslationKey(): ?string
    {
        $translate = $this->failureTranslate;
        $this->failureTranslate = null;

        return $translate;
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
