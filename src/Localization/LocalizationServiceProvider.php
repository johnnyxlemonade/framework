<?php

declare(strict_types=1);

namespace Lemonade\Framework\Localization;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;

final class LocalizationServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(FileTranslator::class, FileTranslator::class);
        $container->singleton(TranslatorInterface::class, FileTranslator::class);

        $container->singleton(LocaleResolver::class, LocaleResolver::class);
        $container->singleton(LocaleResolverInterface::class, LocaleResolver::class);

        $container->singleton('translator', TranslatorInterface::class);
        $container->singleton('locale.resolver', LocaleResolverInterface::class);
    }
}
