<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Session;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Session\Config\SessionConfigDefinition;
use Lemonade\Framework\Session\SessionServiceProvider;
use Lemonade\Framework\Session\Storage\FileSessionStorage;
use Lemonade\Framework\Session\Storage\NativeSessionStorage;
use Lemonade\Framework\Session\Storage\SessionStorageInterface;
use PHPUnit\Framework\TestCase;

final class SessionServiceProviderTest extends TestCase
{
    public function testFileDriverResolvesStorageWritableSessionsDirectory(): void
    {
        $container = $this->containerWithSessionConfig(
            SessionConfigDefinition::create()
                ->driver('file')
                ->filePath('sessions'),
        );

        (new SessionServiceProvider())->register($container);

        $storage = $container->get(SessionStorageInterface::class);

        self::assertInstanceOf(FileSessionStorage::class, $storage);
        self::assertSame(
            '/var/www/framework/storage/writable/sessions',
            $this->readPrivateProperty($storage, 'directory'),
        );
    }

    public function testNativeDriverResolvesStorageWritableSessionsDirectoryOnWindowsPaths(): void
    {
        $container = $this->containerWithSessionConfig(
            SessionConfigDefinition::create()
                ->driver('native')
                ->nativePath('sessions'),
            new ApplicationContext(
                Environment::Testing,
                new Path('C:\\laragon\\www\\framework', 'C:\\laragon\\www\\framework\\public'),
                DebugMode::disabled(),
            ),
        );

        (new SessionServiceProvider())->register($container);

        $storage = $container->get(SessionStorageInterface::class);

        self::assertInstanceOf(NativeSessionStorage::class, $storage);
        self::assertSame(
            'C:\\laragon\\www\\framework\\storage\\writable\\sessions',
            $this->readPrivateProperty($storage, 'savePath'),
        );
    }

    private function containerWithSessionConfig(
        SessionConfigDefinition $definition,
        ?ApplicationContext $context = null,
    ): Container {
        $container = new Container();
        $container->singleton(
            ApplicationContext::class,
            $context ?? new ApplicationContext(
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
