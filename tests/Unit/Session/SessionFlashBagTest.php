<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Session;

use Lemonade\Framework\Session\Contract\SessionInterface;
use Lemonade\Framework\Session\Flash\SessionFlashBag;
use Lemonade\Framework\Session\SessionNamespace;
use PHPUnit\Framework\TestCase;

final class SessionFlashBagTest extends TestCase
{
    public function testFlashValuesAreIsolatedFromOtherSessionData(): void
    {
        $session = new FlashBagTestSession();
        $session->set('outside', 'unchanged');
        $auth = new SessionNamespace($session, 'auth');
        $auth->set('user_id', 42);
        $flash = new SessionFlashBag($session);

        $flash->set('notice', 'Saved');

        self::assertSame('Saved', $flash->get('notice'));
        self::assertSame(['notice' => 'Saved'], $flash->all());
        self::assertFalse($auth->has('notice'));
        self::assertSame(42, $auth->get('user_id'));
        self::assertSame('unchanged', $session->get('outside'));
    }

    public function testClearRemovesOnlyFlashNamespace(): void
    {
        $session = new FlashBagTestSession();
        $session->set('outside', 'unchanged');
        $auth = new SessionNamespace($session, 'auth');
        $auth->set('user_id', 42);
        $flash = new SessionFlashBag($session);
        $flash->set('notice', 'Saved');

        $flash->clear();

        self::assertFalse($flash->has('notice'));
        self::assertSame([], $flash->all());
        self::assertSame(42, $auth->get('user_id'));
        self::assertSame('unchanged', $session->get('outside'));
    }

    public function testPullReturnsAndRemovesValueWhilePreservingFallbackSemantics(): void
    {
        $flash = new SessionFlashBag(new FlashBagTestSession());
        $flash->set('notice', 'Saved');
        $flash->set('nullable', null);

        self::assertSame('Saved', $flash->pull('notice'));
        self::assertFalse($flash->has('notice'));
        self::assertSame('fallback', $flash->pull('notice', 'fallback'));
        self::assertNull($flash->pull('nullable', 'fallback'));
        self::assertFalse($flash->has('nullable'));
    }
}

final class FlashBagTestSession implements SessionInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    public function start(): void {}

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

    public function regenerate(bool $deleteOldSession = true): void {}
}
