<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Validation;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\DatabaseResultInterface;
use Lemonade\Framework\Validation\Rule\RuleRegistry;
use Lemonade\Framework\Validation\Rule\ValidationRuleInterface;
use Lemonade\Framework\Validation\Rule\ValidEmailHeavyRule;
use Lemonade\Framework\Validation\Rule\ValidIcoActiveRule;
use Lemonade\Framework\Validation\Rule\ValidRowIdRule;
use Lemonade\Framework\Validation\ValidationRuleResolver;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final class ValidationRuleResolverTest extends TestCase
{
    public function testResolvesRuleClassThroughContainer(): void
    {
        $registry = new RuleRegistry();
        $registry->addRule('container_rule', ResolverContainerRule::class);

        $container = $this->container();
        $container->singleton(ResolverRuleDependency::class, new ResolverRuleDependency('expected'));

        $resolver = new ValidationRuleResolver($registry, $container);
        $rule = $resolver->resolve('container_rule');

        self::assertInstanceOf(ResolverContainerRule::class, $rule);
        self::assertTrue($rule->validate('expected', null, []));
        self::assertSame($rule, $resolver->resolve('container_rule'));
    }

    public function testThrowsWhenContainerResolvesInvalidRuleObject(): void
    {
        $registry = new RuleRegistry();
        $registry->addRule('invalid_rule', ResolverContainerRule::class);

        $container = $this->container();
        $container->singleton(ResolverContainerRule::class, new \stdClass());

        $resolver = new ValidationRuleResolver($registry, $container);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Resolved validation rule "invalid_rule"');

        $resolver->resolve('invalid_rule');
    }

    public function testDbBackedRuleUsesExplicitDatabaseDependency(): void
    {
        $connection = new ResolverConnection([
            ['checkRowId' => '42'],
        ]);
        $database = new Database($connection, new ResolverDatabaseDriver());
        $rule = new ValidRowIdRule($database);

        self::assertTrue($rule->validate('42', 'users.id', []));
        self::assertSame('SELECT `id` AS checkRowId FROM `users` WHERE `id` = :value LIMIT 1', $connection->lastSql);
        self::assertSame(['value' => '42'], $connection->lastBindings);
    }

    public function testHttpBackedRuleUsesExplicitPsrDependencies(): void
    {
        $client = new ResolverHttpClient(new Response(200, [], '{"valid":true}'));
        $factory = new Psr17Factory();
        $rule = new ValidIcoActiveRule($client, $factory, $factory);

        self::assertTrue($rule->validate('12345678', null, []));
        self::assertInstanceOf(RequestInterface::class, $client->lastRequest);
        self::assertSame('https://api.core1.agency/validator/company?value=12345678', (string) $client->lastRequest->getUri());
    }

    public function testFailureDetailsAreConsumedOnCachedRuleInstance(): void
    {
        $client = new ResolverHttpClient(new Response(200, [], '{"valid":false,"translate":"blacklist"}'));
        $factory = new Psr17Factory();
        $rule = new ValidEmailHeavyRule($client, $factory, $factory);

        self::assertFalse($rule->validate('blocked@example.test', null, []));
        self::assertSame('blacklist', $rule->pullFailureTranslationKey());
        self::assertNull($rule->pullFailureTranslationKey());
    }

    public function testValidationSourceDoesNotUseGlobalServiceResolving(): void
    {
        $root = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'src/Validation';
        $violations = [];

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo || $file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            if ($contents === false) {
                continue;
            }

            if (str_contains($contents, 'service(') || str_contains($contents, 'ServiceLocator')) {
                $violations[] = str_replace('\\', '/', $file->getPathname());
            }
        }

        self::assertSame([], $violations);
    }

    private function container(): Container
    {
        $container = new Container();
        $container->singleton(ContainerInterface::class, $container);

        return $container;
    }
}

final class ResolverRuleDependency
{
    public function __construct(
        public readonly string $expected,
    ) {}
}

final class ResolverContainerRule implements ValidationRuleInterface
{
    public function __construct(
        private readonly ResolverRuleDependency $dependency,
    ) {}

    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($param, $data);

        return $value === $this->dependency->expected;
    }
}

final class ResolverConnection implements ConnectionInterface
{
    public string $lastSql = '';

    /** @var array<int|string, mixed> */
    public array $lastBindings = [];

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function __construct(private readonly array $rows) {}

    public function select(string $sql, array $bindings = []): array
    {
        $this->lastSql = $sql;
        $this->lastBindings = $bindings;

        return $this->rows;
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

final class ResolverDatabaseDriver implements DatabaseDriverInterface
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

    public function version(): string
    {
        return 'test';
    }
}

final class ResolverHttpClient implements ClientInterface
{
    public ?RequestInterface $lastRequest = null;

    public function __construct(private readonly ResponseInterface $response) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->lastRequest = $request;

        return $this->response;
    }
}
