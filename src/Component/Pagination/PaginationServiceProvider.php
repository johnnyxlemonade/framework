<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Pagination;

use Lemonade\Framework\Component\Support\ComponentConfig;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Localization\TranslatorInterface;
use Psr\Http\Message\ServerRequestInterface;

final class PaginationServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(PaginationFactory::class, static function (ContainerInterface $container): PaginationFactory {
            /** @var Config $config */
            $config = $container->get(Config::class);

            return new PaginationFactory(
                request: $container->get(ServerRequestInterface::class),
                defaultPerPage: $config->int('pagination.default_per_page', 20),
                maxPerPage: $config->int('pagination.max_per_page', 200),
            );
        });
        $container->singleton(PaginationRenderer::class, static function (ContainerInterface $container): PaginationRenderer {
            /** @var Config $config */
            $config = $container->get(Config::class);
            $classes = $config->array('pagination.classes', []);

            return new PaginationRenderer(
                classes: ComponentConfig::normalizeClasses($classes),
                translator: $container->get(TranslatorInterface::class),
                visiblePages: $config->int('pagination.visible_pages', 5),
                showFirstLast: $config->bool('pagination.show_first_last', true),
            );
        });
        $container->singleton(PaginationComponent::class, PaginationComponent::class);
    }
}
