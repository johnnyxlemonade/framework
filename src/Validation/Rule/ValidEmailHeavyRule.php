<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule;

use Lemonade\Framework\Validation\Rule\Traits\JsonFetchTrait;

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

    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($param, $data);

        $this->failureTranslate = null;

        if (!is_string($value) || trim($value) === '') {
            $this->failureTranslate = 'missing';

            return false;
        }

        $email = trim($value);

        $url = sprintf(
            'https://api.core1.agency/validator/email?id=%s',
            rawurlencode($email),
        );

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
}
