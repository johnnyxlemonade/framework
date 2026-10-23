<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Session;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\ScopeKind;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Session\Config\SessionConfigDefinition;
use Lemonade\Framework\Session\Contract\SessionInterface;
use Lemonade\Framework\Session\Flash\FlashBagInterface;
use Lemonade\Framework\Session\Flash\SessionFlashBag;
use Lemonade\Framework\Session\Native\NativeSession;
use Lemonade\Framework\Session\SessionFactory;
use Lemonade\Framework\Session\SessionNamespace;
use Lemonade\Framework\Session\SessionServiceProvider;
use Lemonade\Framework\Session\Storage\FileSessionStorage;
use Lemonade\Framework\Session\Storage\SessionStorageInterface;
use PHPUnit\Framework\TestCase;

final class SessionServiceProviderTest extends TestCase
{
    public function testItUsesSessionFactoryForCanonicalDefaultSession(): void
    {
        $container = $this->containerWithSessionConfig(
            SessionConfigDefinition::create()
                ->driver('file')
                ->filePath('consumer-sessions'),
        );
        $factory = new SessionFactory(
            new ApplicationContext(
                Environment::Testing,
                new Path('/factory/context', '/factory/context/public'),
                DebugMode::disabled(),
            ),
            $container,
        );
        $container->singleton(SessionFactory::class, $factory);

        (new SessionServiceProvider())->register($container);
        self::assertFalse($container->isBound(SessionStorageInterface::class));
        $scope = $container->beginScope(ScopeKind::Request);

        try {
            $session = $scope->get(SessionInterface::class);
            $flash = $scope->get(FlashBagInterface::class);

            self::assertInstanceOf(NativeSession::class, $session);
            self::assertSame($session, $scope->get(SessionInterface::class));
            self::assertInstanceOf(SessionFlashBag::class, $flash);
            self::assertSame($flash, $scope->get(FlashBagInterface::class));

            $flashNamespace = $this->readPrivateProperty($flash, 'namespace');

            self::assertInstanceOf(SessionNamespace::class, $flashNamespace);
            self::assertSame($session, $this->readPrivateProperty($flashNamespace, 'session'));

            $storage = $this->readPrivateProperty($session, 'storage');

            self::assertInstanceOf(FileSessionStorage::class, $storage);
            self::assertSame(
                '/factory/context/storage/writable/consumer-sessions',
                $this->readPrivateProperty($storage, 'directory'),
            );
        } finally {
            $scope->close();
        }
    }

    public function testNonDatabaseDefaultSessionDoesNotRequireDatabaseConnection(): void
    {
        $container = $this->containerWithSessionConfig(
            SessionConfigDefinition::create()->driver('native'),
        );

        (new SessionServiceProvider())->register($container);
        $scope = $container->beginScope(ScopeKind::Request);

        try {
            self::assertInstanceOf(NativeSession::class, $scope->get(SessionInterface::class));
        } finally {
            $scope->close();
        }
    }

    public function testRequestScopesOwnIndependentCanonicalSessionGraphs(): void
    {
        $container = $this->containerWithSessionConfig(
            SessionConfigDefinition::create()->driver('file'),
        );
        (new SessionServiceProvider())->register($container);

        $firstScope = $container->beginScope(ScopeKind::Request);
        $secondScope = $container->beginScope(ScopeKind::Request);

        try {
            $firstSession = $firstScope->get(SessionInterface::class);
            $secondSession = $secondScope->get(SessionInterface::class);

            self::assertSame($firstSession, $firstScope->get(SessionInterface::class));
            self::assertNotSame($firstSession, $secondSession);
            self::assertNotSame(
                $this->readPrivateProperty($firstSession, 'storage'),
                $this->readPrivateProperty($secondSession, 'storage'),
            );

            $firstFlashNamespace = $this->readPrivateProperty(
                $firstScope->get(FlashBagInterface::class),
                'namespace',
            );
            $secondFlashNamespace = $this->readPrivateProperty(
                $secondScope->get(FlashBagInterface::class),
                'namespace',
            );

            self::assertSame($firstSession, $this->readPrivateProperty($firstFlashNamespace, 'session'));
            self::assertSame($secondSession, $this->readPrivateProperty($secondFlashNamespace, 'session'));
            self::assertNotSame(
                $firstScope->get(FlashBagInterface::class),
                $secondScope->get(FlashBagInterface::class),
            );
        } finally {
            $firstScope->close();
            $secondScope->close();
        }
    }

    public function testInvalidCookiePolicyFailsDuringProviderBootstrap(): void
    {
        $container = $this->containerWithSessionConfig(
            SessionConfigDefinition::fromArrayData([
                'cookie' => ['secure' => 'sometimes'],
            ]),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid session cookie secure value "sometimes"');

        (new SessionServiceProvider())->register($container);
    }

    private function containerWithSessionConfig(SessionConfigDefinition $definition): Container
    {
        $container = new Container();
        $container->singleton(
            ApplicationContext::class,
            new ApplicationContext(
                Environment::Testing,
                new Path('/var/www/framework', '/var/www/framework/public'),
                DebugMode::disabled(),
            ),
        );

        $registry = new ConfigDefinitionRegistry();
        $registry->addDefinition($definition);
        $container->singleton(ConfigDefinitionRegistry::class, $registry);

        return $container;
    }

    private function readPrivateProperty(object $object, string $property): mixed
    {
        $reflection = new \ReflectionObject($object);
        $instanceProperty = $reflection->getProperty($property);

        return $instanceProperty->getValue($object);
    }
}
