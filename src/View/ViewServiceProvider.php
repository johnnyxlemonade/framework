<?php

declare(strict_types=1);

namespace Lemonade\Framework\View;

use Lemonade\Framework\Component\ComponentRegistry;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Http\ResponseBuilder;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Image\ImageViewHelper;
use Lemonade\Framework\Localization\Config\LocalizationConfig;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Routing\UrlGenerator;
use Lemonade\Framework\Security\Csrf\CsrfViewHelper;
use Lemonade\Framework\Session\Contract\SessionInterface;
use Lemonade\Framework\Session\Flash\FlashBagInterface;
use Lemonade\Framework\Support\BaseUrlResolver;
use Lemonade\Framework\View\Config\ViewConfig;
use Lemonade\Framework\View\Config\ViewConfigDefinition;
use Lemonade\Framework\View\Config\ViewConfigResolver;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Registers the reusable view prototype and request-scoped rendering graph.
 */
final class ViewServiceProvider implements ServiceProviderInterface
{
    /**
     * Keeps filesystem resources app-scoped while isolating request, session, and CSRF state per scope.
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $container->singleton(ViewResourceRegistry::class, ViewResourceRegistry::class);
        $container->scoped(RequestViewHelpers::class, static function (ContainerInterface $container): RequestViewHelpers {
            $flash = $container->has(FlashBagInterface::class) ? $container->get(FlashBagInterface::class) : null;
            $session = $container->has(SessionInterface::class) ? $container->get(SessionInterface::class) : null;

            return new RequestViewHelpers(
                request: $container->get(ServerRequestInterface::class),
                urlGenerator: $container->get(UrlGenerator::class),
                flash: $flash instanceof FlashBagInterface ? $flash : null,
                session: $session instanceof SessionInterface ? $session : null,
                absoluteUrl: static fn(string $path): string => $container
                    ->get(BaseUrlResolver::class)
                    ->baseUrl($path),
            );
        });
        $container->scoped(ViewRendererInterface::class, static function (ContainerInterface $container): ViewRendererInterface {
            $view = $container->get(View::class)->forRequest(
                $container->get(ViewHelpers::class),
                $container->get(RequestViewHelpers::class),
                [
                    'csrf' => $container->get(CsrfViewHelper::class),
                    'component' => $container->get(ComponentRegistry::class),
                ],
            );

            return new PhpViewRenderer(
                $view,
                new ResponseBuilder(
                    $container->get(ResponseFactoryInterface::class),
                    $container->get(StreamFactoryInterface::class),
                ),
            );
        });
        $container->singleton(ViewConfigResolver::class, ViewConfigResolver::class);
        $container->singleton(ViewConfig::class, static function (ContainerInterface $container): ViewConfig {
            return $container
                ->get(ViewConfigResolver::class)
                ->resolve(...$container->get(ConfigDefinitionRegistry::class)->typedEntriesFor(
                    ViewConfigDefinition::moduleKey(),
                    ViewConfigDefinition::class,
                ));
        });

        $container->scoped(ViewHelpers::class, static fn(ContainerInterface $container): ViewHelpers => new ViewHelpers(
            urlGenerator: $container->get(UrlGenerator::class),
            csrf: $container->get(CsrfViewHelper::class),
            translator: $container->get(TranslatorInterface::class),
            config: $container->get(LocalizationConfig::class),
            images: $container->has(ImageViewHelper::class) ? $container->get(ImageViewHelper::class) : null,
        ));

        $container->singleton(View::class, static function (ContainerInterface $container): View {
            $configuredBasePath = $container->get(ViewConfig::class)->basePath;
            $resolvedBasePath = $configuredBasePath;

            if ($container->has(ApplicationContext::class)) {
                $resolvedBasePath = $container
                    ->get(ApplicationContext::class)
                    ->path($configuredBasePath);
            }

            $view = new View(
                $resolvedBasePath,
                $container->get(ViewResourceRegistry::class),
            );

            $view->share('component', $container->get(ComponentRegistry::class));
            $view->share('url', $container->get(UrlGenerator::class));

            return $view;
        });
    }
}
