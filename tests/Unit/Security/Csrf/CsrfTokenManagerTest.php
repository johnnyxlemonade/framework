<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Security\Csrf;

use Lemonade\Framework\Security\Csrf\CsrfTokenManager;
use Lemonade\Framework\Session\Contract\SessionInterface;
use Lemonade\Framework\Session\SessionNamespace;
use PHPUnit\Framework\TestCase;

final class CsrfTokenManagerTest extends TestCase
{
    public function testItKeepsMultipleNamedTokensInOneLogicalNamespace(): void
    {
        $manager = new CsrfTokenManager(new CsrfTokenManagerSession());

        $login = $manager->token('login');
        $profile = $manager->token('profile');

        self::assertNotSame($login, $profile);
        self::assertSame($login, $manager->token('login'));
        self::assertSame($profile, $manager->token('profile'));
        self::assertTrue($manager->validate($login, 'login'));
        self::assertTrue($manager->validate($profile, 'profile'));
        self::assertFalse($manager->validate($login, 'profile'));
    }

    public function testForgetRemovesOnlyTheRequestedToken(): void
    {
        $manager = new CsrfTokenManager(new CsrfTokenManagerSession());
        $login = $manager->token('login');
        $profile = $manager->token('profile');

        $manager->forget('login');

        self::assertFalse($manager->validate($login, 'login'));
        self::assertTrue($manager->validate($profile, 'profile'));
        self::assertSame($profile, $manager->token('profile'));
        self::assertNotSame($login, $manager->token('login'));
    }

    public function testCsrfDataDoesNotAffectOtherSessionNamespacesOrParentData(): void
    {
        $session = new CsrfTokenManagerSession();
        $session->set('outside', 'unchanged');
        $auth = new SessionNamespace($session, 'auth');
        $auth->set('user_id', 42);
        $manager = new CsrfTokenManager($session);

        $manager->token('login');
        $manager->regenerate('login');
        $manager->forget('login');

        self::assertSame(42, $auth->get('user_id'));
        self::assertSame('unchanged', $session->get('outside'));
    }
}

final class CsrfTokenManagerSession implements SessionInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    public function start(): void
    {
    }

    public function started(): bool
    {
        return true;
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

    public function regenerate(bool $deleteOldSession = true): void
    {
    }
}
