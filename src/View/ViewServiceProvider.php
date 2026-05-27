<?php

declare(strict_types=1);

namespace Lemonade\Framework\View;

use Lemonade\Framework\Component\ComponentRegistry;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Routing\UrlGenerator;
use Lemonade\Framework\Security\Csrf\CsrfViewHelper;
use Lemonade\Framework\Support\BaseUrlResolver;

final class ViewServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(View::class, static function (ContainerInterface $container): View {
            $config = $container->get(Config::class);

            $basePathConfig = $config->get('view.base_path', 'app/Views');
            $basePath = is_scalar($basePathConfig) ? (string) $basePathConfig : 'app/Views';

            $view = new View($basePath);

            $view->share('component', $container->get(ComponentRegistry::class));
            $view->share('baseUrl', $container->get(BaseUrlResolver::class));
            $view->share('url', $container->get(UrlGenerator::class));
            $view->share('csrf', $container->get(CsrfViewHelper::class));

            return $view;
        });
    }
}
