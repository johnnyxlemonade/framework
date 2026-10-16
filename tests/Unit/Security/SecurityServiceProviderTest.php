<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Security;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\ScopeKind;
use Lemonade\Framework\Security\Csrf\CsrfMiddleware;
use Lemonade\Framework\Security\Csrf\CsrfTokenManager;
use Lemonade\Framework\Security\Csrf\CsrfViewHelper;
use Lemonade\Framework\Security\SecurityServiceProvider;
use Lemonade\Framework\Session\Contract\SessionInterface;
use Lemonade\Framework\Session\SessionNamespace;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class SecurityServiceProviderTest extends TestCase
{
    public function testCsrfServicesShareCanonicalDefaultSessionAndManager(): void
    {
        $container = new Container();
        $container->scoped(SessionInterface::class, SecurityProviderSession::class);
        $container->singleton(Psr17Factory::class, new Psr17Factory());

        (new SecurityServiceProvider())->register($container);
        $firstScope = $container->beginScope(ScopeKind::Request);
        $secondScope = $container->beginScope(ScopeKind::Request);

        try {
            $session = $firstScope->get(SessionInterface::class);
            $manager = $firstScope->get(CsrfTokenManager::class);
            $middleware = $firstScope->get(CsrfMiddleware::class);
            $viewHelper = $firstScope->get(CsrfViewHelper::class);
            $namespace = $this->readPrivateProperty($manager, 'tokens');

            self::assertSame($manager, $firstScope->get(CsrfTokenManager::class));
            self::assertInstanceOf(SessionNamespace::class, $namespace);
            self::assertSame($session, $this->readPrivateProperty($namespace, 'session'));
            self::assertSame($manager, $this->readPrivateProperty($middleware, 'tokens'));
            self::assertSame($manager, $this->readPrivateProperty($viewHelper, 'tokens'));

            $secondManager = $secondScope->get(CsrfTokenManager::class);
            $secondNamespace = $this->readPrivateProperty($secondManager, 'tokens');
            $secondMiddleware = $secondScope->get(CsrfMiddleware::class);
            $secondViewHelper = $secondScope->get(CsrfViewHelper::class);

            self::assertNotSame($manager, $secondManager);
            self::assertNotSame($middleware, $secondMiddleware);
            self::assertNotSame($viewHelper, $secondViewHelper);
            self::assertNotSame($session, $secondScope->get(SessionInterface::class));
            self::assertSame(
                $secondScope->get(SessionInterface::class),
                $this->readPrivateProperty($secondNamespace, 'session'),
            );
            self::assertSame($secondManager, $this->readPrivateProperty($secondMiddleware, 'tokens'));
            self::assertSame($secondManager, $this->readPrivateProperty($secondViewHelper, 'tokens'));
        } finally {
            $firstScope->close();
            $secondScope->close();
        }
    }

    private function readPrivateProperty(object $object, string $property): mixed
    {
        $reflection = new \ReflectionObject($object);
        $instanceProperty = $reflection->getProperty($property);

        return $instanceProperty->getValue($object);
    }
}

final class SecurityProviderSession implements SessionInterface
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
