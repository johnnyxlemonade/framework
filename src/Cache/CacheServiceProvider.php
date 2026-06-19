<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cache;

use Lemonade\Framework\Cache\Config\CacheConfig;
use Lemonade\Framework\Cache\Config\CacheConfigDefinition;
use Lemonade\Framework\Cache\Config\CacheConfigResolver;
use Lemonade\Framework\Cache\Store\ArrayCacheItemPool;
use Lemonade\Framework\Cache\Store\FileCacheItemPool;
use Lemonade\Framework\Cache\Store\NullCacheItemPool;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Filesystem\Contract\DirectoryManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use RuntimeException;

use function sprintf;

final class CacheServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(CacheConfigResolver::class, CacheConfigResolver::class);
        $container->singleton(CacheConfig::class, static function (ContainerInterface $container): CacheConfig {
            return $container
                ->get(CacheConfigResolver::class)
                ->resolve(...$container->get(ConfigDefinitionRegistry::class)->typedEntriesFor(
                    CacheConfigDefinition::moduleKey(),
                    CacheConfigDefinition::class,
                ));
        });
        $container->singleton(ArrayCacheItemPool::class, ArrayCacheItemPool::class);
        $container->singleton(NullCacheItemPool::class, NullCacheItemPool::class);

        $container->singleton(FileCacheItemPool::class, static function (ContainerInterface $container): FileCacheItemPool {
            $config = $container->get(CacheConfig::class);
            $context = $container->get(ApplicationContext::class);

            $path = $context->resolveStoragePath(
                $config->fileStore->path,
            );

            return new FileCacheItemPool(
                directory: $path,
                directoryManager: $container->get(DirectoryManagerInterface::class),
            );
        });

        $container->singleton(CacheItemPoolInterface::class, static function (ContainerInterface $container): CacheItemPoolInterface {
            $config = $container->get(CacheConfig::class);
            $default = $config->defaultStore;

            return match ($default) {
                'array' => $container->get(ArrayCacheItemPool::class),
                'null' => $container->get(NullCacheItemPool::class),
                'file' => $container->get(FileCacheItemPool::class),
                default => throw new RuntimeException(sprintf('Unsupported cache store "%s".', $default)),
            };
        });

        $container->singleton(CacheManager::class, static function (ContainerInterface $container): CacheManager {
            return new CacheManager(
                $container->get(CacheItemPoolInterface::class),
            );
        });

        $container->singleton('cache', CacheManager::class);
        $container->singleton('cache.pool', CacheItemPoolInterface::class);
    }
}
