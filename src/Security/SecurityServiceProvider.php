<?php

declare(strict_types=1);

namespace Lemonade\Framework\Security;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Security\Csrf\CsrfMiddleware;
use Lemonade\Framework\Security\Csrf\CsrfTokenManager;
use Lemonade\Framework\Security\Csrf\CsrfViewHelper;

final class SecurityServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(CsrfTokenManager::class, CsrfTokenManager::class);
        $container->singleton(CsrfMiddleware::class, CsrfMiddleware::class);
        $container->singleton(CsrfViewHelper::class, CsrfViewHelper::class);
        $container->singleton('csrf', CsrfViewHelper::class);
    }
}
