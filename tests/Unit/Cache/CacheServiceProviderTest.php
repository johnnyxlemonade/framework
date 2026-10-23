<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Cache;

use Lemonade\Framework\Cache\CacheServiceProvider;
use Lemonade\Framework\Cache\Config\CacheConfigDefinition;
use Lemonade\Framework\Cache\Exception\CacheStorageException;
use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Core\Config\AppConfig;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Filesystem\Contract\DirectoryManagerInterface;
use Lemonade\Framework\Filesystem\Manager\DirectoryManager;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;

final class CacheServiceProviderTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = rtrim(sys_get_temp_dir(), '/\\')
            . DIRECTORY_SEPARATOR
            . 'lemonade-cache-provider-'
            . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        $this->deleteRecursive($this->root);
    }

    public function testProviderSuppliesZeroConfigurationIntegrityKeyAndApplicationBoundaries(): void
    {
        $container = $this->container(
            CacheConfigDefinition::create()
                ->defaultStore('file')
                ->fileStore('cache/framework', 'provider-test', 300),
        );
        $pool = $container->get(CacheItemPoolInterface::class);
        $item = $pool->getItem('provider');
        $item->set('value');

        self::assertTrue($pool->save($item));
        self::assertSame('value', $pool->getItem('provider')->get());
        self::assertFileExists(
            $this->root
            . DIRECTORY_SEPARATOR
            . 'storage'
            . DIRECTORY_SEPARATOR
            . 'writable'
            . DIRECTORY_SEPARATOR
            . 'cache'
            . DIRECTORY_SEPARATOR
            . 'keys'
            . DIRECTORY_SEPARATOR
            . 'file-cache.key',
        );
    }

    public function testProviderRejectsConfiguredTraversalBeforeCacheUse(): void
    {
        $container = $this->container(
            CacheConfigDefinition::create()
                ->defaultStore('file')
                ->fileStore('../public/cache'),
        );

        $this->expectException(CacheStorageException::class);
        $container->get(CacheItemPoolInterface::class);
    }

    private function container(CacheConfigDefinition $definition): Container
    {
        $container = new Container();
        $registry = new ConfigDefinitionRegistry();
        $registry->addDefinition($definition);
        $context = new ApplicationContext(
            Environment::Testing,
            new Path(
                $this->root,
                $this->root . DIRECTORY_SEPARATOR . 'public',
            ),
            DebugMode::disabled(),
        );
        $container->singleton(ConfigDefinitionRegistry::class, $registry);
        $container->singleton(ApplicationContext::class, $context);
        $container->singleton(
            AppConfig::class,
            new AppConfig(
                timezone: null,
                baseUrl: null,
                basePath: $this->root,
                publicPath: $this->root . DIRECTORY_SEPARATOR . 'public',
                env: 'testing',
                debug: false,
                appPath: $this->root . DIRECTORY_SEPARATOR . 'app',
                configPath: $this->root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Config',
                storagePath: $this->root . DIRECTORY_SEPARATOR . 'storage',
                secret: null,
            ),
        );
        $container->singleton(DirectoryManagerInterface::class, new DirectoryManager());
        (new CacheServiceProvider())->register($container);

        return $container;
    }

    private function deleteRecursive(string $path): void
    {
        if (!file_exists($path) && !is_link($path)) {
            return;
        }

        if (is_file($path) || is_link($path)) {
            @unlink($path);

            return;
        }

        $items = scandir($path);

        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $this->deleteRecursive($path . DIRECTORY_SEPARATOR . $item);
        }

        @rmdir($path);
    }
}
