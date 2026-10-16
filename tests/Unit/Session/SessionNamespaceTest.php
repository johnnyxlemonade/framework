<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Session;

use InvalidArgumentException;
use Lemonade\Framework\Session\Contract\SessionInterface;
use Lemonade\Framework\Session\SessionNamespace;
use LogicException;
use PHPUnit\Framework\TestCase;

final class SessionNamespaceTest extends TestCase
{
    public function testItStoresReadsAndChecksNamespacedValues(): void
    {
        $session = new NamespaceTestSession();
        $namespace = new SessionNamespace($session, 'auth');

        $namespace->set('user_id', 42);
        $namespace->set('nullable', null);

        self::assertTrue($namespace->has('user_id'));
        self::assertSame(42, $namespace->get('user_id'));
        self::assertTrue($namespace->has('nullable'));
        self::assertNull($namespace->get('nullable', 'fallback'));
        self::assertSame('fallback', $namespace->get('missing', 'fallback'));
        self::assertSame(
            [
                'user_id' => 42,
                'nullable' => null,
            ],
            $namespace->all(),
        );
    }

    public function testRemoveDeletesOnlyOneKeyInItsNamespace(): void
    {
        $session = new NamespaceTestSession();
        $namespace = new SessionNamespace($session, 'auth');
        $namespace->set('user_id', 42);
        $namespace->set('role', 'editor');

        $namespace->remove('user_id');

        self::assertFalse($namespace->has('user_id'));
        self::assertSame('editor', $namespace->get('role'));
    }

    public function testClearRemovesOnlyItsNamespace(): void
    {
        $session = new NamespaceTestSession();
        $session->set('outside', 'unchanged');

        $auth = new SessionNamespace($session, 'auth');
        $mfa = new SessionNamespace($session, 'mfa');
        $auth->set('user_id', 42);
        $mfa->set('challenge', 'x');

        $auth->clear();

        self::assertFalse($auth->has('user_id'));
        self::assertSame('x', $mfa->get('challenge'));
        self::assertSame('unchanged', $session->get('outside'));
    }

    public function testNamespacesDoNotAffectEachOtherWhenUsingTheSamePublicKey(): void
    {
        $session = new NamespaceTestSession();
        $auth = new SessionNamespace($session, 'auth');
        $mfa = new SessionNamespace($session, 'mfa');

        $auth->set('status', 'authenticated');
        $mfa->set('status', 'pending');
        $auth->remove('status');

        self::assertFalse($auth->has('status'));
        self::assertSame('pending', $mfa->get('status'));
    }

    public function testItRejectsInvalidNamespaceName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid session namespace name "auth.profile".');

        new SessionNamespace(new NamespaceTestSession(), 'auth.profile');
    }

    public function testItSafelyNormalizesCorruptNamespacePayload(): void
    {
        $session = new NamespaceTestSession();
        $namespace = new SessionNamespace($session, 'auth');
        $namespace->set('user_id', 42);
        $session->replaceOnlyNamespacedPayload('corrupt');

        self::assertFalse($namespace->has('user_id'));
        self::assertSame('fallback', $namespace->get('user_id', 'fallback'));

        $namespace->set('role', 'editor');

        self::assertSame('editor', $namespace->get('role'));
        self::assertFalse($namespace->has('user_id'));
    }
}

final class NamespaceTestSession implements SessionInterface
{
    private bool $started = false;

    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    public function start(): void
    {
        $this->started = true;
    }

    public function started(): bool
    {
        return $this->started;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->data)
            ? $this->data[$key]
            : $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    public function clear(): void
    {
        $this->data = [];
    }

    public function destroy(): void
    {
        $this->data = [];
    }

    public function regenerate(bool $deleteOldSession = true): void {}

    public function replaceOnlyNamespacedPayload(mixed $payload): void
    {
        $namespaceKeys = array_values(array_filter(
            array_keys($this->data),
            static fn(string $key): bool => $key !== 'outside',
        ));

        if (count($namespaceKeys) !== 1) {
            throw new LogicException('Expected exactly one namespace payload.');
        }

        $this->data[$namespaceKeys[0]] = $payload;
    }
}
