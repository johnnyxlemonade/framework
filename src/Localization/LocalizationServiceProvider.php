<?php

declare(strict_types=1);

namespace Lemonade\Framework\Localization;

use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Localization\Config\LocalizationConfig;
use Lemonade\Framework\Localization\Config\LocalizationConfigDefinition;
use Lemonade\Framework\Localization\Config\LocalizationConfigResolver;

final class LocalizationServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilderInterface $container): void
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

        $container->singleton(TranslationResourceRegistry::class, TranslationResourceRegistry::class);
        $container->singleton(
            TranslationSourceCatalogInterface::class,
            static fn(ContainerInterface $container): FileTranslationSourceCatalog => new FileTranslationSourceCatalog(
                context: $container->get(ApplicationContext::class),
                resources: $container->get(TranslationResourceRegistry::class),
            ),
        );
        $container->singleton(TranslationOverrideProviderInterface::class, NullTranslationOverrideProvider::class);
        $container->singleton(
            FileTranslator::class,
            static fn(ContainerInterface $container): FileTranslator => new FileTranslator(
                context: $container->get(ApplicationContext::class),
                config: $container->get(LocalizationConfig::class),
                resources: $container->get(TranslationResourceRegistry::class),
                sources: $container->get(TranslationSourceCatalogInterface::class),
                overrides: $container->get(TranslationOverrideProviderInterface::class),
            ),
        );
        $container->singleton(
            TranslatorInterface::class,
            static fn(ContainerInterface $container): FileTranslator => $container->get(FileTranslator::class),
        );

        $container->singleton(LocaleResolver::class, LocaleResolver::class);
        $container->singleton(LocaleResolverInterface::class, LocaleResolver::class);
    }
}
