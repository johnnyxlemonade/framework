<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Validation;

use Lemonade\Framework\Validation\Endpoint\RecaptchaEndpointProviderInterface;
use Lemonade\Framework\Validation\Rule\RecaptchaRule;
use Lemonade\Framework\Tests\Unit\Validation\Support\RecaptchaSpyEndpointProvider;
use Lemonade\Framework\Tests\Unit\Validation\Support\RecaptchaStaticEndpointProvider;
use Lemonade\Framework\Tests\Unit\Validation\Support\RecaptchaThrowingEndpointProvider;
use Lemonade\Framework\Tests\Unit\Validation\Support\ValidationQueueHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class RecaptchaRuleTest extends TestCase
{
    public function testNotConfiguredProviderFailsClosedWithoutHttpRequest(): void
    {
        $client = new ValidationQueueHttpClient();
        $rule = $this->rule($client, new RecaptchaThrowingEndpointProvider());

        self::assertFalse($rule->validate('token-123', 'secret-456', []));
        self::assertSame(0, $client->requestCount);
    }

    public function testMissingSecretReturnsFalseWithoutEndpointLookup(): void
    {
        $client = new ValidationQueueHttpClient();
        $provider = new RecaptchaSpyEndpointProvider('https://validator.example.test/recaptcha/verify');
        $rule = $this->rule($client, $provider);

        self::assertFalse($rule->validate('token-123', '  ', []));
        self::assertSame(0, $provider->callCount);
        self::assertSame(0, $client->requestCount);
    }

    public function testMissingTokenReturnsFalseWithoutEndpointLookup(): void
    {
        $client = new ValidationQueueHttpClient();
        $provider = new RecaptchaSpyEndpointProvider('https://validator.example.test/recaptcha/verify');
        $rule = $this->rule($client, $provider);

        self::assertFalse($rule->validate('  ', 'secret-456', []));
        self::assertSame(0, $provider->callCount);
        self::assertSame(0, $client->requestCount);
    }

    public function testUsesExactUrlFromInjectedProvider(): void
    {
        $client = new ValidationQueueHttpClient(new Response(200, [], '{"success":true}'));
        $provider = new RecaptchaSpyEndpointProvider('https://validator.example.test/recaptcha/check?mode=custom');
        $rule = $this->rule($client, $provider);

        self::assertTrue($rule->validate(' token-123 ', ' secret-456 ', []));
        self::assertSame(1, $provider->callCount);
        self::assertSame(
            'https://validator.example.test/recaptcha/check?mode=custom',
            (string) $client->lastRequest?->getUri(),
        );
    }

    public function testSuccessfulResponseKeepsSecretAndResponsePayloadWithoutRemoteIp(): void
    {
        $client = new ValidationQueueHttpClient(new Response(200, [], '{"success":true}'));
        $rule = $this->rule(
            $client,
            new RecaptchaStaticEndpointProvider('https://validator.example.test/recaptcha/verify'),
        );

        $originalServer = $_SERVER;
        $_SERVER = [];

        try {
            self::assertTrue($rule->validate(' token-123 ', ' secret-456 ', []));
        } finally {
            $_SERVER = $originalServer;
        }

        self::assertNotNull($client->lastRequest);
        self::assertSame('POST', $client->lastRequest->getMethod());
        self::assertSame(
            'secret=secret-456&response=token-123',
            (string) $client->lastRequest->getBody(),
        );
    }

    public function testSuccessfulResponseAddsOptionalRemoteIpPayload(): void
    {
        $client = new ValidationQueueHttpClient(new Response(200, [], '{"success":true}'));
        $rule = $this->rule(
            $client,
            new RecaptchaStaticEndpointProvider('https://validator.example.test/recaptcha/verify'),
        );

        $originalServer = $_SERVER;
        $_SERVER['REMOTE_ADDR'] = ' 203.0.113.10 ';

        try {
            self::assertTrue($rule->validate('token-123', 'secret-456', []));
        } finally {
            $_SERVER = $originalServer;
        }

        self::assertSame(
            'secret=secret-456&response=token-123&remoteip=203.0.113.10',
            (string) $client->lastRequest?->getBody(),
        );
    }

    public function testInvalidJsonReturnsFalse(): void
    {
        $client = new ValidationQueueHttpClient(new Response(200, [], '{broken'));
        $rule = $this->rule(
            $client,
            new RecaptchaStaticEndpointProvider('https://validator.example.test/recaptcha/verify'),
        );

        self::assertFalse($rule->validate('token-123', 'secret-456', []));
    }

    public function testNonSuccessHttpStatusReturnsFalse(): void
    {
        $client = new ValidationQueueHttpClient(new Response(503, [], '{"success":true}'));
        $rule = $this->rule(
            $client,
            new RecaptchaStaticEndpointProvider('https://validator.example.test/recaptcha/verify'),
        );

        self::assertFalse($rule->validate('token-123', 'secret-456', []));
    }

    public function testUnsuccessfulApiResponseReturnsFalse(): void
    {
        $client = new ValidationQueueHttpClient(new Response(200, [], '{"success":false}'));
        $rule = $this->rule(
            $client,
            new RecaptchaStaticEndpointProvider('https://validator.example.test/recaptcha/verify'),
        );

        self::assertFalse($rule->validate('token-123', 'secret-456', []));
    }

    private function rule(
        ValidationQueueHttpClient $client,
        RecaptchaEndpointProviderInterface $provider,
    ): RecaptchaRule {
        $factory = new Psr17Factory();

        return new RecaptchaRule($client, $factory, $factory, $provider);
    }
}
