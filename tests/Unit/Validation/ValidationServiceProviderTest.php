<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Validation;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\Config\AutowireMode;
use Lemonade\Framework\Container\Config\ContainerConfig;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\DatabaseResultInterface;
use Lemonade\Framework\Validation\Endpoint\DefaultRecaptchaEndpointProvider;
use Lemonade\Framework\Validation\Endpoint\DefaultVatValidationEndpointProvider;
use Lemonade\Framework\Validation\Endpoint\DefaultValidationEndpointProvider;
use Lemonade\Framework\Validation\Endpoint\RecaptchaEndpointProviderInterface;
use Lemonade\Framework\Validation\Endpoint\VatValidationEndpointProviderInterface;
use Lemonade\Framework\Validation\Endpoint\ValidationEndpointProviderInterface;
use Lemonade\Framework\Validation\Rule\RecaptchaRule;
use Lemonade\Framework\Validation\Rule\RuleRegistry;
use Lemonade\Framework\Validation\Rule\ValidationRuleInterface;
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

    public function testRegistersAndResolvesEveryBuiltInRuleInStrictMode(): void
    {
        $container = $this->strictContainer();
        $factory = new Psr17Factory();
        $container->singleton(RequestFactoryInterface::class, $factory);
        $container->singleton(StreamFactoryInterface::class, $factory);
        $container->singleton(ClientInterface::class, new ValidationQueueHttpClient());
        $container->singleton(
            Database::class,
            new Database(
                new ValidationServiceProviderConnection(),
                new ValidationServiceProviderDatabaseDriver(),
            ),
        );
        (new ValidationServiceProvider())->register($container);

        $registry = $container->get(RuleRegistry::class);
        foreach (RuleRegistry::builtInRules() as $name => $ruleClass) {
            self::assertTrue($container->isBound($ruleClass));
            self::assertSame($ruleClass, $registry->get($name));
            self::assertInstanceOf(ValidationRuleInterface::class, $container->get($ruleClass));
        }
    }

    private function container(): Container
    {
        $container = new Container();
        $container->singleton(ContainerInterface::class, $container);

        return $container;
    }

    private function strictContainer(): Container
    {
        $container = $this->container();
        $container->instance(ContainerConfig::class, new ContainerConfig(AutowireMode::Strict));

        return $container;
    }
}

final class ValidationServiceProviderConnection implements ConnectionInterface
{
    public function select(string $sql, array $bindings = []): array
    {
        unset($sql, $bindings);

        return [];
    }

    public function cursor(string $sql, array $bindings = []): \Generator
    {
        unset($sql, $bindings);

        yield from [];
    }

    public function statement(string $sql, array $bindings = []): int
    {
        unset($sql, $bindings);

        return 0;
    }

    public function beginTransaction(): void {}

    public function commit(): void {}

    public function rollBack(): void {}

    public function inTransaction(): bool
    {
        return false;
    }

    public function transaction(callable $callback): mixed
    {
        return $callback($this);
    }

    public function lastInsertId(): int|string|null
    {
        return null;
    }

    public function affectedRows(): int
    {
        return 0;
    }

    public function reconnect(): void {}

    public function close(): void {}

    public function serverVersion(): string
    {
        return 'test';
    }

    public function escapeString(string $value): string
    {
        return addslashes($value);
    }
}

final class ValidationServiceProviderDatabaseDriver implements DatabaseDriverInterface
{
    public function query(string $sql, array|false $binds = false): DatabaseResultInterface|bool
    {
        unset($sql, $binds);

        return false;
    }

    public function cursor(string $sql, array|false $binds = false): \Generator
    {
        unset($sql, $binds);

        yield from [];
    }

    public function simple_query(string $sql): bool
    {
        unset($sql);

        return false;
    }

    public function affected_rows(): int
    {
        return 0;
    }

    public function insert_id(): int|string|null
    {
        return null;
    }

    public function escape(mixed $value): string
    {
        if (is_scalar($value) || $value instanceof \Stringable) {
            return (string) $value;
        }

        return '';
    }

    public function escape_str(string $value, bool $like = false): string
    {
        unset($like);

        return $value;
    }

    public function escape_like_str(string $value): string
    {
        return $value;
    }

    public function escape_identifiers(string $item): string
    {
        return $item;
    }

    public function protect_identifiers(
        string $item,
        bool $prefixSingle = false,
        ?bool $protectIdentifiers = null,
        bool $fieldExists = true,
    ): string {
        unset($prefixSingle, $protectIdentifiers, $fieldExists);

        return $item;
    }

    public function platform(): string
    {
        return 'test';
    }

    public function trans_begin(bool $test_mode = false): bool
    {
        unset($test_mode);

        return true;
    }

    public function trans_commit(): bool
    {
        return true;
    }

    public function trans_rollback(): bool
    {
        return true;
    }

    public function trans_status(): bool
    {
        return true;
    }

    public function trans_off(): void {}

    public function trans_strict(bool $mode = true): void
    {
        unset($mode);
    }

    public function version(): string
    {
        return 'test';
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
