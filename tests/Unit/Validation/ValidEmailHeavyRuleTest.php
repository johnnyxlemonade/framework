<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Validation;

use Lemonade\Framework\Validation\Endpoint\ValidationEndpointProviderInterface;
use Lemonade\Framework\Validation\Rule\ValidEmailHeavyRule;
use Lemonade\Framework\Tests\Unit\Validation\Support\ValidationQueueHttpClient;
use Lemonade\Framework\Tests\Unit\Validation\Support\ValidationSpyEndpointProvider;
use Lemonade\Framework\Tests\Unit\Validation\Support\ValidationStaticEndpointProvider;
use Lemonade\Framework\Tests\Unit\Validation\Support\ValidationThrowingEndpointProvider;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;

final class ValidEmailHeavyRuleTest extends TestCase
{
    public function testEmptyValueReturnsFalseAndMissing(): void
    {
        $client = new ValidationQueueHttpClient();
        $rule = $this->rule($client, new ValidationStaticEndpointProvider(
            emailUrl: 'https://validator.example.test/email?email=ignored',
            companyUrl: 'https://validator.example.test/company/ignored',
        ));

        self::assertFalse($rule->validate('', null, []));
        self::assertSame('missing', $rule->pullFailureTranslationKey());
        self::assertSame(0, $client->requestCount);
    }

    public function testNonStringValueReturnsFalseAndMissing(): void
    {
        $client = new ValidationQueueHttpClient();
        $rule = $this->rule($client, new ValidationStaticEndpointProvider(
            emailUrl: 'https://validator.example.test/email?email=ignored',
            companyUrl: 'https://validator.example.test/company/ignored',
        ));

        self::assertFalse($rule->validate(123, null, []));
        self::assertSame('missing', $rule->pullFailureTranslationKey());
        self::assertSame(0, $client->requestCount);
    }

    public function testValidResponseReturnsTrue(): void
    {
        $client = new ValidationQueueHttpClient(new Response(200, [], '{"valid":true}'));
        $rule = $this->rule($client, new ValidationStaticEndpointProvider(
            emailUrl: 'https://validator.example.test/email?email=test%40example.com',
            companyUrl: 'https://validator.example.test/company/ignored',
        ));

        self::assertTrue($rule->validate(' test@example.com ', null, []));
        self::assertSame('https://validator.example.test/email?email=test%40example.com', (string) $client->lastRequest?->getUri());
    }

    public function testUnavailableHttpEndpointReturnsFalseAndUnavailable(): void
    {
        $client = new ValidationQueueHttpClient();
        $client->throw = true;
        $rule = $this->rule($client, new ValidationStaticEndpointProvider(
            emailUrl: 'https://validator.example.test/email?email=test',
            companyUrl: 'https://validator.example.test/company/ignored',
        ));

        self::assertFalse($rule->validate('test@example.com', null, []));
        self::assertSame('unavailable', $rule->pullFailureTranslationKey());
    }

    public function testNotConfiguredProviderReturnsFalseAndUnavailable(): void
    {
        $client = new ValidationQueueHttpClient();
        $rule = $this->rule($client, new ValidationThrowingEndpointProvider());

        self::assertFalse($rule->validate('test@example.com', null, []));
        self::assertSame('unavailable', $rule->pullFailureTranslationKey());
        self::assertSame(0, $client->requestCount);
    }

    public function testInvalidJsonReturnsFalseAndUnavailable(): void
    {
        $client = new ValidationQueueHttpClient(new Response(200, [], '{invalid json'));
        $rule = $this->rule($client, new ValidationStaticEndpointProvider(
            emailUrl: 'https://validator.example.test/email?email=test',
            companyUrl: 'https://validator.example.test/company/ignored',
        ));

        self::assertFalse($rule->validate('test@example.com', null, []));
        self::assertSame('unavailable', $rule->pullFailureTranslationKey());
    }

    public function testNonSuccessHttpStatusReturnsFalseAndUnavailable(): void
    {
        $client = new ValidationQueueHttpClient(new Response(503, [], '{"valid":true}'));
        $rule = $this->rule($client, new ValidationStaticEndpointProvider(
            emailUrl: 'https://validator.example.test/email?email=test',
            companyUrl: 'https://validator.example.test/company/ignored',
        ));

        self::assertFalse($rule->validate('test@example.com', null, []));
        self::assertSame('unavailable', $rule->pullFailureTranslationKey());
    }

    public function testTranslateMappingsReturnExpectedInternalKeys(): void
    {
        foreach ([
            'missing' => 'missing',
            'error' => 'syntax',
            'blacklist' => 'blacklist',
            'spam' => 'spam',
            'checkdnsrr' => 'dns',
        ] as $apiTranslate => $expected) {
            $client = new ValidationQueueHttpClient(
                new Response(200, [], sprintf('{"valid":false,"translate":"%s"}', $apiTranslate)),
            );
            $rule = $this->rule($client, new ValidationStaticEndpointProvider(
                emailUrl: 'https://validator.example.test/email?email=test',
                companyUrl: 'https://validator.example.test/company/ignored',
            ));

            self::assertFalse($rule->validate('test@example.com', null, []));
            self::assertSame($expected, $rule->pullFailureTranslationKey());
        }
    }

    public function testUnknownTranslateReturnsInvalid(): void
    {
        $client = new ValidationQueueHttpClient(new Response(200, [], '{"valid":false,"translate":"weird"}'));
        $rule = $this->rule($client, new ValidationStaticEndpointProvider(
            emailUrl: 'https://validator.example.test/email?email=test',
            companyUrl: 'https://validator.example.test/company/ignored',
        ));

        self::assertFalse($rule->validate('test@example.com', null, []));
        self::assertSame('invalid', $rule->pullFailureTranslationKey());
    }

    public function testMissingTranslateReturnsInvalid(): void
    {
        $client = new ValidationQueueHttpClient(new Response(200, [], '{"valid":false}'));
        $rule = $this->rule($client, new ValidationStaticEndpointProvider(
            emailUrl: 'https://validator.example.test/email?email=test',
            companyUrl: 'https://validator.example.test/company/ignored',
        ));

        self::assertFalse($rule->validate('test@example.com', null, []));
        self::assertSame('invalid', $rule->pullFailureTranslationKey());
    }

    public function testRequestUrlComesFromInjectedProvider(): void
    {
        $provider = new ValidationSpyEndpointProvider(
            emailUrl: 'https://validator.example.test/custom/email/path',
            companyUrl: 'https://validator.example.test/company/ignored',
        );
        $client = new ValidationQueueHttpClient(new Response(200, [], '{"valid":true}'));
        $rule = $this->rule($client, $provider);

        self::assertTrue($rule->validate('  test@example.com  ', null, []));
        self::assertSame('test@example.com', $provider->lastEmail);
        self::assertSame('https://validator.example.test/custom/email/path', (string) $client->lastRequest?->getUri());
    }

    public function testRuleDoesNotModifyProviderQueryParameters(): void
    {
        $url = 'https://validator.example.test/email/check?email=test%40example.com&token=a%2Bb&mode=custom';
        $client = new ValidationQueueHttpClient(new Response(200, [], '{"valid":true}'));
        $rule = $this->rule($client, new ValidationStaticEndpointProvider(
            emailUrl: $url,
            companyUrl: 'https://validator.example.test/company/ignored',
        ));

        self::assertTrue($rule->validate('test@example.com', null, []));
        self::assertSame($url, (string) $client->lastRequest?->getUri());
    }

    public function testFailureTranslationKeyIsClearedAfterFirstRead(): void
    {
        $client = new ValidationQueueHttpClient(new Response(200, [], '{"valid":false,"translate":"blacklist"}'));
        $rule = $this->rule($client, new ValidationStaticEndpointProvider(
            emailUrl: 'https://validator.example.test/email?email=test',
            companyUrl: 'https://validator.example.test/company/ignored',
        ));

        self::assertFalse($rule->validate('test@example.com', null, []));
        self::assertSame('blacklist', $rule->pullFailureTranslationKey());
        self::assertNull($rule->pullFailureTranslationKey());
    }

    private function rule(
        ClientInterface $client,
        ValidationEndpointProviderInterface $provider,
    ): ValidEmailHeavyRule {
        $factory = new Psr17Factory();

        return new ValidEmailHeavyRule($client, $factory, $factory, $provider);
    }
}
