<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Meta;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Core\ServiceProviderInterface;

final class MetaServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(MetaComponent::class, static function (ContainerInterface $container): MetaComponent {
            /** @var Config $config */
            $config = $container->get(Config::class);

            return new MetaComponent($config);
        });
    }
}
