<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Validation;

use Lemonade\Framework\Validation\Endpoint\ValidationEndpointProviderInterface;
use Lemonade\Framework\Validation\Rule\ValidIcoActiveRule;
use Lemonade\Framework\Tests\Unit\Validation\Support\ValidationQueueHttpClient;
use Lemonade\Framework\Tests\Unit\Validation\Support\ValidationSpyEndpointProvider;
use Lemonade\Framework\Tests\Unit\Validation\Support\ValidationStaticEndpointProvider;
use Lemonade\Framework\Tests\Unit\Validation\Support\ValidationThrowingEndpointProvider;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class ValidIcoActiveRuleTest extends TestCase
{
    public function testNonStringValueReturnsFalse(): void
    {
        $client = new ValidationQueueHttpClient();
        $rule = $this->rule($client, new ValidationStaticEndpointProvider(
            emailUrl: 'https://validator.example.test/email/ignored',
            companyUrl: 'https://validator.example.test/company/ignored',
        ));

        self::assertFalse($rule->validate(12345678, null, []));
        self::assertSame(0, $client->requestCount);
    }

    public function testEmptyValueReturnsFalse(): void
    {
        $client = new ValidationQueueHttpClient();
        $rule = $this->rule($client, new ValidationStaticEndpointProvider(
            emailUrl: 'https://validator.example.test/email/ignored',
            companyUrl: 'https://validator.example.test/company/ignored',
        ));

        self::assertFalse($rule->validate('', null, []));
        self::assertSame(0, $client->requestCount);
    }

    public function testIcoWithInvalidLengthReturnsFalse(): void
    {
        $client = new ValidationQueueHttpClient();
        $rule = $this->rule($client, new ValidationStaticEndpointProvider(
            emailUrl: 'https://validator.example.test/email/ignored',
            companyUrl: 'https://validator.example.test/company/ignored',
        ));

        self::assertFalse($rule->validate('1234567', null, []));
        self::assertSame(0, $client->requestCount);
    }

    public function testIcoWithNonDigitReturnsFalse(): void
    {
        $client = new ValidationQueueHttpClient();
        $rule = $this->rule($client, new ValidationStaticEndpointProvider(
            emailUrl: 'https://validator.example.test/email/ignored',
            companyUrl: 'https://validator.example.test/company/ignored',
        ));

        self::assertFalse($rule->validate('1234A678', null, []));
        self::assertSame(0, $client->requestCount);
    }

    public function testWhitespaceIsRemovedBeforeProviderCall(): void
    {
        $provider = new ValidationSpyEndpointProvider(
            emailUrl: 'https://validator.example.test/email/ignored',
            companyUrl: 'https://validator.example.test/company/12345678',
        );
        $client = new ValidationQueueHttpClient(new Response(200, [], '{"valid":true}'));
        $rule = $this->rule($client, $provider);

        self::assertTrue($rule->validate(" 1234 5678 \t", null, []));
        self::assertSame('12345678', $provider->lastIco);
    }

    public function testInvalidLocalValueDoesNotPerformHttpRequest(): void
    {
        $client = new ValidationQueueHttpClient();
        $rule = $this->rule($client, new ValidationStaticEndpointProvider(
            emailUrl: 'https://validator.example.test/email/ignored',
            companyUrl: 'https://validator.example.test/company/ignored',
        ));

        self::assertFalse($rule->validate('12 34A678', null, []));
        self::assertSame(0, $client->requestCount);
    }

    public function testNotConfiguredProviderReturnsFalse(): void
    {
        $client = new ValidationQueueHttpClient();
        $rule = $this->rule($client, new ValidationThrowingEndpointProvider());

        self::assertFalse($rule->validate('12345678', null, []));
        self::assertSame(0, $client->requestCount);
    }

    public function testValidResponseReturnsTrue(): void
    {
        $client = new ValidationQueueHttpClient(new Response(200, [], '{"valid":true}'));
        $rule = $this->rule($client, new ValidationStaticEndpointProvider(
            emailUrl: 'https://validator.example.test/email/ignored',
            companyUrl: 'https://validator.example.test/company/12345678',
        ));

        self::assertTrue($rule->validate('12345678', null, []));
        self::assertSame('https://validator.example.test/company/12345678', (string) $client->lastRequest?->getUri());
    }

    public function testInvalidApiResponseReturnsFalse(): void
    {
        $client = new ValidationQueueHttpClient(new Response(200, [], '{"valid":false}'));
        $rule = $this->rule($client, new ValidationStaticEndpointProvider(
            emailUrl: 'https://validator.example.test/email/ignored',
            companyUrl: 'https://validator.example.test/company/12345678',
        ));

        self::assertFalse($rule->validate('12345678', null, []));
    }

    public function testInvalidJsonReturnsFalse(): void
    {
        $client = new ValidationQueueHttpClient(new Response(200, [], 'not-json'));
        $rule = $this->rule($client, new ValidationStaticEndpointProvider(
            emailUrl: 'https://validator.example.test/email/ignored',
            companyUrl: 'https://validator.example.test/company/12345678',
        ));

        self::assertFalse($rule->validate('12345678', null, []));
    }

    public function testNonSuccessHttpStatusReturnsFalse(): void
    {
        $client = new ValidationQueueHttpClient(new Response(404, [], '{"valid":true}'));
        $rule = $this->rule($client, new ValidationStaticEndpointProvider(
            emailUrl: 'https://validator.example.test/email/ignored',
            companyUrl: 'https://validator.example.test/company/12345678',
        ));

        self::assertFalse($rule->validate('12345678', null, []));
    }

    public function testRequestUrlComesFromInjectedProvider(): void
    {
        $provider = new ValidationSpyEndpointProvider(
            emailUrl: 'https://validator.example.test/email/ignored',
            companyUrl: 'https://validator.example.test/company/check?value=12345678',
        );
        $client = new ValidationQueueHttpClient(new Response(200, [], '{"valid":true}'));
        $rule = $this->rule($client, $provider);

        self::assertTrue($rule->validate('12345678', null, []));
        self::assertSame('12345678', $provider->lastIco);
        self::assertSame('https://validator.example.test/company/check?value=12345678', (string) $client->lastRequest?->getUri());
    }

    public function testRuleDoesNotModifyProviderQueryParameters(): void
    {
        $url = 'https://validator.example.test/company/check/12345678?mode=custom&token=a%2Bb';
        $client = new ValidationQueueHttpClient(new Response(200, [], '{"valid":true}'));
        $rule = $this->rule($client, new ValidationStaticEndpointProvider(
            emailUrl: 'https://validator.example.test/email/ignored',
            companyUrl: $url,
        ));

        self::assertTrue($rule->validate('12345678', null, []));
        self::assertSame($url, (string) $client->lastRequest?->getUri());
    }

    private function rule(
        ValidationQueueHttpClient $client,
        ValidationEndpointProviderInterface $provider,
    ): ValidIcoActiveRule {
        $factory = new Psr17Factory();

        return new ValidIcoActiveRule($client, $factory, $factory, $provider);
    }
}
