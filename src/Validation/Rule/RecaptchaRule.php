<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule;

use Lemonade\Framework\Validation\Rule\Traits\JsonFetchTrait;

final class RecaptchaRule implements ValidationRuleInterface
{
    use JsonFetchTrait;

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

        $json = $this->postForm(
            'https://www.google.com/recaptcha/api/siteverify',
            $payload,
        );

        if ($json === null) {
            return false;
        }

        return ($json['success'] ?? false) === true;
    }
}
