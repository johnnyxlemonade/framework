<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Validation;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Validation\Endpoint\DefaultRecaptchaEndpointProvider;
use Lemonade\Framework\Validation\Endpoint\DefaultVatValidationEndpointProvider;
use Lemonade\Framework\Validation\Endpoint\DefaultValidationEndpointProvider;
use Lemonade\Framework\Validation\Endpoint\RecaptchaEndpointProviderInterface;
use Lemonade\Framework\Validation\Endpoint\VatValidationEndpointProviderInterface;
use Lemonade\Framework\Validation\Endpoint\ValidationEndpointProviderInterface;
use Lemonade\Framework\Validation\Rule\RecaptchaRule;
use Lemonade\Framework\Validation\Rule\ValidDicActiveRule;
use Lemonade\Framework\Validation\Rule\ValidEmailHeavyRule;
use Lemonade\Framework\Validation\Rule\ValidIcoActiveRule;
use Lemonade\Framework\Validation\ValidationServiceProvider;
use Lemonade\Framework\Tests\Unit\Validation\Support\ValidationQueueHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class ValidationServiceProviderTest extends TestCase
{
    public function testRegistersDefaultValidationEndpointProviderBinding(): void
    {
        $container = $this->container();
        (new ValidationServiceProvider())->register($container);

        self::assertInstanceOf(
            DefaultValidationEndpointProvider::class,
            $container->get(ValidationEndpointProviderInterface::class),
        );
    }

    public function testApplicationCanOverrideValidationEndpointProviderBinding(): void
    {
        $container = $this->container();
        (new ValidationServiceProvider())->register($container);
        $container->singleton(ValidationEndpointProviderInterface::class, ValidationServiceProviderCustomEndpointProvider::class);

        self::assertInstanceOf(
            ValidationServiceProviderCustomEndpointProvider::class,
            $container->get(ValidationEndpointProviderInterface::class),
        );
    }

    public function testRegistersDefaultRecaptchaEndpointProviderBinding(): void
    {
        $container = $this->container();
        (new ValidationServiceProvider())->register($container);

        self::assertInstanceOf(
            DefaultRecaptchaEndpointProvider::class,
            $container->get(RecaptchaEndpointProviderInterface::class),
        );
    }

    public function testApplicationCanOverrideRecaptchaEndpointProviderBinding(): void
    {
        $container = $this->container();
        (new ValidationServiceProvider())->register($container);
        $container->singleton(RecaptchaEndpointProviderInterface::class, ValidationServiceProviderCustomRecaptchaEndpointProvider::class);

        self::assertInstanceOf(
            ValidationServiceProviderCustomRecaptchaEndpointProvider::class,
            $container->get(RecaptchaEndpointProviderInterface::class),
        );
    }

    public function testRegistersDefaultVatValidationEndpointProviderBinding(): void
    {
        $container = $this->container();
        (new ValidationServiceProvider())->register($container);

        self::assertInstanceOf(
            DefaultVatValidationEndpointProvider::class,
            $container->get(VatValidationEndpointProviderInterface::class),
        );
    }

    public function testApplicationCanOverrideVatValidationEndpointProviderBinding(): void
    {
        $container = $this->container();
        (new ValidationServiceProvider())->register($container);
        $container->singleton(VatValidationEndpointProviderInterface::class, ValidationServiceProviderCustomVatEndpointProvider::class);

        self::assertInstanceOf(
            ValidationServiceProviderCustomVatEndpointProvider::class,
            $container->get(VatValidationEndpointProviderInterface::class),
        );
    }

    public function testContainerCreatesRemoteValidationRulesWithAllDependencies(): void
    {
        $container = $this->container();
        $factory = new Psr17Factory();
        $container->singleton(RequestFactoryInterface::class, $factory);
        $container->singleton(StreamFactoryInterface::class, $factory);
        $container->singleton(ClientInterface::class, new ValidationQueueHttpClient());
        (new ValidationServiceProvider())->register($container);

        self::assertInstanceOf(ValidEmailHeavyRule::class, $container->get(ValidEmailHeavyRule::class));
        self::assertInstanceOf(ValidIcoActiveRule::class, $container->get(ValidIcoActiveRule::class));
        self::assertInstanceOf(ValidDicActiveRule::class, $container->get(ValidDicActiveRule::class));
        self::assertInstanceOf(RecaptchaRule::class, $container->get(RecaptchaRule::class));
    }

    private function container(): Container
    {
        $container = new Container();
        $container->singleton(ContainerInterface::class, $container);

        return $container;
    }
}

final class ValidationServiceProviderCustomEndpointProvider implements ValidationEndpointProviderInterface
{
    public function emailValidationUrl(string $email): string
    {
        return 'https://validator.example.test/email/' . rawurlencode($email);
    }

    public function activeCompanyValidationUrl(string $ico): string
    {
        return 'https://validator.example.test/company/' . rawurlencode($ico);
    }
}

final class ValidationServiceProviderCustomRecaptchaEndpointProvider implements RecaptchaEndpointProviderInterface
{
    public function verificationUrl(): string
    {
        return 'https://validator.example.test/recaptcha/verify';
    }
}

final class ValidationServiceProviderCustomVatEndpointProvider implements VatValidationEndpointProviderInterface
{
    public function validationUrl(string $countryCode, string $vatNumber): string
    {
        return 'https://validator.example.test/vat/'
            . rawurlencode($countryCode)
            . '/'
            . rawurlencode($vatNumber);
    }
}
