<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Validation;

use Lemonade\Framework\Tests\Unit\Validation\Support\ValidationQueueHttpClient;
use Lemonade\Framework\Tests\Unit\Validation\Support\VatSpyEndpointProvider;
use Lemonade\Framework\Tests\Unit\Validation\Support\VatStaticEndpointProvider;
use Lemonade\Framework\Tests\Unit\Validation\Support\VatThrowingEndpointProvider;
use Lemonade\Framework\Validation\Endpoint\VatValidationEndpointProviderInterface;
use Lemonade\Framework\Validation\Rule\ValidDicActiveRule;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class ValidDicActiveRuleTest extends TestCase
{
    public function testNonStringValueReturnsFalse(): void
    {
        $client = new ValidationQueueHttpClient();
        $rule = $this->rule($client, new VatStaticEndpointProvider('https://validator.example.test/vat/ignored'));

        self::assertFalse($rule->validate(123, null, []));
        self::assertSame(0, $client->requestCount);
    }

    public function testTooShortVatReturnsFalse(): void
    {
        $client = new ValidationQueueHttpClient();
        $rule = $this->rule($client, new VatStaticEndpointProvider('https://validator.example.test/vat/ignored'));

        self::assertFalse($rule->validate('CZ1', null, []));
        self::assertSame(0, $client->requestCount);
    }

    public function testInvalidCountryReturnsFalse(): void
    {
        $client = new ValidationQueueHttpClient();
        $rule = $this->rule($client, new VatStaticEndpointProvider('https://validator.example.test/vat/ignored'));

        self::assertFalse($rule->validate('1Z12345678', null, []));
        self::assertSame(0, $client->requestCount);
    }

    public function testMissingNumberReturnsFalse(): void
    {
        $client = new ValidationQueueHttpClient();
        $rule = $this->rule($client, new VatStaticEndpointProvider('https://validator.example.test/vat/ignored'));

        self::assertFalse($rule->validate('CZ', null, []));
        self::assertSame(0, $client->requestCount);
    }

    public function testWhitespaceIsRemovedAndCountryIsUppercasedBeforeProviderCall(): void
    {
        $provider = new VatSpyEndpointProvider('https://validator.example.test/vat/CZ/12345678');
        $client = new ValidationQueueHttpClient(new Response(200, [], '{"isValid":true}'));
        $rule = $this->rule($client, $provider);

        self::assertTrue($rule->validate(" cz 1234 5678 \t", null, []));
        self::assertSame('CZ', $provider->lastCountryCode);
        self::assertSame('12345678', $provider->lastVatNumber);
    }

    public function testNotConfiguredProviderReturnsFalseWithoutHttpRequest(): void
    {
        $client = new ValidationQueueHttpClient();
        $rule = $this->rule($client, new VatThrowingEndpointProvider());

        self::assertFalse($rule->validate('CZ12345678', null, []));
        self::assertSame(0, $client->requestCount);
    }

    public function testValidResponseReturnsTrue(): void
    {
        $client = new ValidationQueueHttpClient(new Response(200, [], '{"isValid":true}'));
        $rule = $this->rule(
            $client,
            new VatStaticEndpointProvider('https://validator.example.test/vat/CZ/12345678'),
        );

        self::assertTrue($rule->validate('CZ12345678', null, []));
        self::assertSame('https://validator.example.test/vat/CZ/12345678', (string) $client->lastRequest?->getUri());
    }

    public function testInvalidApiResponseReturnsFalse(): void
    {
        $client = new ValidationQueueHttpClient(new Response(200, [], '{"isValid":false}'));
        $rule = $this->rule(
            $client,
            new VatStaticEndpointProvider('https://validator.example.test/vat/CZ/12345678'),
        );

        self::assertFalse($rule->validate('CZ12345678', null, []));
    }

    public function testInvalidJsonReturnsFalse(): void
    {
        $client = new ValidationQueueHttpClient(new Response(200, [], 'not-json'));
        $rule = $this->rule(
            $client,
            new VatStaticEndpointProvider('https://validator.example.test/vat/CZ/12345678'),
        );

        self::assertFalse($rule->validate('CZ12345678', null, []));
    }

    public function testNonSuccessHttpStatusReturnsFalse(): void
    {
        $client = new ValidationQueueHttpClient(new Response(503, [], '{"isValid":true}'));
        $rule = $this->rule(
            $client,
            new VatStaticEndpointProvider('https://validator.example.test/vat/CZ/12345678'),
        );

        self::assertFalse($rule->validate('CZ12345678', null, []));
    }

    public function testRequestUrlComesFromInjectedProvider(): void
    {
        $provider = new VatSpyEndpointProvider('https://validator.example.test/custom/vat/check?mode=custom');
        $client = new ValidationQueueHttpClient(new Response(200, [], '{"isValid":true}'));
        $rule = $this->rule($client, $provider);

        self::assertTrue($rule->validate('CZ12345678', null, []));
        self::assertSame('CZ', $provider->lastCountryCode);
        self::assertSame('12345678', $provider->lastVatNumber);
        self::assertSame('https://validator.example.test/custom/vat/check?mode=custom', (string) $client->lastRequest?->getUri());
    }

    public function testRuleDoesNotModifyProviderQueryParameters(): void
    {
        $url = 'https://validator.example.test/vat/check/CZ/12345678?mode=custom&token=a%2Bb';
        $client = new ValidationQueueHttpClient(new Response(200, [], '{"isValid":true}'));
        $rule = $this->rule($client, new VatStaticEndpointProvider($url));

        self::assertTrue($rule->validate('CZ12345678', null, []));
        self::assertSame($url, (string) $client->lastRequest?->getUri());
    }

    private function rule(
        ValidationQueueHttpClient $client,
        VatValidationEndpointProviderInterface $provider,
    ): ValidDicActiveRule {
        $factory = new Psr17Factory();

        return new ValidDicActiveRule($client, $factory, $factory, $provider);
    }
}
