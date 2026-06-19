<?php

declare(strict_types=1);

namespace Lemonade\Framework\View;

use Lemonade\Framework\Component\ComponentRegistry;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Localization\Config\LocalizationConfig;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Routing\UrlGenerator;
use Lemonade\Framework\Security\Csrf\CsrfViewHelper;
use Lemonade\Framework\Support\BaseUrlResolver;
use Lemonade\Framework\View\Config\ViewConfig;
use Lemonade\Framework\View\Config\ViewConfigDefinition;
use Lemonade\Framework\View\Config\ViewConfigResolver;

final class ViewServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(ViewConfigResolver::class, ViewConfigResolver::class);
        $container->singleton(ViewConfig::class, static function (ContainerInterface $container): ViewConfig {
            return $container
                ->get(ViewConfigResolver::class)
                ->resolve(...$container->get(\Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry::class)->typedEntriesFor(
                    ViewConfigDefinition::moduleKey(),
                    ViewConfigDefinition::class,
                ));
        });

        $container->singleton(ViewHelpers::class, static fn(ContainerInterface $container): ViewHelpers => new ViewHelpers(
            baseUrl: $container->get(BaseUrlResolver::class),
            urlGenerator: $container->get(UrlGenerator::class),
            csrf: $container->get(CsrfViewHelper::class),
            translator: $container->get(TranslatorInterface::class),
            config: $container->get(LocalizationConfig::class),
        ));

        $container->singleton(View::class, static function (ContainerInterface $container): View {
            $view = new View($container->get(ViewConfig::class)->basePath);

            $view->share('helpers', $container->get(ViewHelpers::class));
            $view->share('component', $container->get(ComponentRegistry::class));
            $view->share('baseUrl', $container->get(BaseUrlResolver::class));
            $view->share('url', $container->get(UrlGenerator::class));
            $view->share('csrf', $container->get(CsrfViewHelper::class));

            return $view;
        });
    }
}
