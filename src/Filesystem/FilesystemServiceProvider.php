<?php

declare(strict_types=1);

namespace Lemonade\Framework\Filesystem;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Filesystem\Contract\DirectoryManagerInterface;
use Lemonade\Framework\Filesystem\Contract\FileManagerInterface;
use Lemonade\Framework\Filesystem\Contract\LockManagerInterface;
use Lemonade\Framework\Filesystem\Manager\DirectoryManager;
use Lemonade\Framework\Filesystem\Manager\FileManager;
use Lemonade\Framework\Filesystem\Manager\LockManager;

final class FilesystemServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(DirectoryManagerInterface::class, DirectoryManager::class);
        $container->singleton(FileManagerInterface::class, FileManager::class);
        $container->singleton(LockManagerInterface::class, LockManager::class);
        $container->singleton(Filesystem::class, Filesystem::class);

        $container->singleton('filesystem', static function (ContainerInterface $container): Filesystem {
            return $container->get(Filesystem::class);
        });

        $container->singleton('files', static function (ContainerInterface $container): FileManagerInterface {
            return $container->get(FileManagerInterface::class);
        });

        $container->singleton('directories', static function (ContainerInterface $container): DirectoryManagerInterface {
            return $container->get(DirectoryManagerInterface::class);
        });

        $container->singleton('locks', static function (ContainerInterface $container): LockManagerInterface {
            return $container->get(LockManagerInterface::class);
        });
    }
}
