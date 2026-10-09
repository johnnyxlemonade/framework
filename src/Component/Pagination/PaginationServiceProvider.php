<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Pagination;

use Lemonade\Framework\Component\Pagination\Config\PaginationConfig;
use Lemonade\Framework\Component\Pagination\Config\PaginationConfigDefinition;
use Lemonade\Framework\Component\Pagination\Config\PaginationConfigResolver;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\ProviderContainerAssertions;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Localization\TranslatorInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Registers resolved pagination configuration and request-scoped pagination services.
 */
final class PaginationServiceProvider implements ServiceProviderInterface
{
    /**
     * Registers pagination services while keeping request-dependent factory and component instances scoped.
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $builder = ProviderContainerAssertions::builder($container, self::class, 'request-scoped services');

        $container->singleton(PaginationConfigResolver::class, PaginationConfigResolver::class);
        $container->singleton(PaginationConfig::class, static function (ContainerInterface $container): PaginationConfig {
            return $container
                ->get(PaginationConfigResolver::class)
                ->resolve(...$container->get(ConfigDefinitionRegistry::class)->typedEntriesFor(
                    PaginationConfigDefinition::moduleKey(),
                    PaginationConfigDefinition::class,
                ));
        });
        $builder->scoped(PaginationFactory::class, static function (ContainerInterface $container): PaginationFactory {
            $config = $container->get(PaginationConfig::class);

            return new PaginationFactory(
                request: $container->get(ServerRequestInterface::class),
                defaultPerPage: $config->defaultPerPage,
                maxPerPage: $config->maxPerPage,
            );
        });
        $container->singleton(PaginationRenderer::class, static function (ContainerInterface $container): PaginationRenderer {
            $config = $container->get(PaginationConfig::class);

            return new PaginationRenderer(
                classes: $config->classes,
                translator: $container->get(TranslatorInterface::class),
                visiblePages: $config->visiblePages,
                showFirstLast: $config->showFirstLast,
            );
        });
        $builder->scoped(PaginationComponent::class, PaginationComponent::class);
    }

}
