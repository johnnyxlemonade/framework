<?php

declare(strict_types=1);

namespace Lemonade\Framework\Localization;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Localization\Config\LocalizationConfig;
use Lemonade\Framework\Localization\Config\LocalizationConfigDefinition;
use Lemonade\Framework\Localization\Config\LocalizationConfigResolver;

final class LocalizationServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(LocalizationConfigResolver::class, LocalizationConfigResolver::class);
        $container->singleton(LocalizationConfig::class, static function (ContainerInterface $container): LocalizationConfig {
            return $container
                ->get(LocalizationConfigResolver::class)
                ->resolve(...$container->get(ConfigDefinitionRegistry::class)->typedEntriesFor(
                    LocalizationConfigDefinition::moduleKey(),
                    LocalizationConfigDefinition::class,
                ));
        });

        $container->singleton(FileTranslator::class, FileTranslator::class);
        $container->singleton(TranslatorInterface::class, FileTranslator::class);

        $container->singleton(LocaleResolver::class, LocaleResolver::class);
        $container->singleton(LocaleResolverInterface::class, LocaleResolver::class);

        $container->singleton('translator', TranslatorInterface::class);
        $container->singleton('locale.resolver', LocaleResolverInterface::class);
    }
}
