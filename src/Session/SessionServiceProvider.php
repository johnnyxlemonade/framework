<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session;

use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Http\Request\ResolvedRequestContext;
use Lemonade\Framework\Session\Config\SessionConfig;
use Lemonade\Framework\Session\Config\SessionConfigDefinition;
use Lemonade\Framework\Session\Config\SessionConfigResolver;
use Lemonade\Framework\Session\Contract\SessionInterface;
use Lemonade\Framework\Session\Flash\FlashBagInterface;
use Lemonade\Framework\Session\Flash\SessionFlashBag;

/**
 * Registers the host application's canonical session and flash bag services
 */
final readonly class SessionServiceProvider implements ServiceProviderInterface
{
    /**
     * Binds resolved host configuration to the default session and flash bag contracts
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $config = $container
            ->get(SessionConfigResolver::class)
            ->resolve(...$container->get(ConfigDefinitionRegistry::class)->typedEntriesFor(
                SessionConfigDefinition::moduleKey(),
                SessionConfigDefinition::class,
            ));
        $container->singleton(SessionConfig::class, $config);
        if (!$container->isBound(SessionFactory::class)) {
            $container->scoped(SessionFactory::class, static function (ContainerInterface $container): SessionFactory {
                $requestContext = $container->has(ResolvedRequestContext::class)
                    ? $container->get(ResolvedRequestContext::class)
                    : null;

                return new SessionFactory(
                    context: $container->get(ApplicationContext::class),
                    container: $container,
                    requestContext: $requestContext,
                );
            });
        }
        $container->scoped(SessionInterface::class, static function (ContainerInterface $container): SessionInterface {
            return $container
                ->get(SessionFactory::class)
                ->create($container->get(SessionConfig::class));
        });

        $container->scoped(FlashBagInterface::class, SessionFlashBag::class);
    }
}
