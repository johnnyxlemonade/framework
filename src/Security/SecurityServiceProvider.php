<?php

declare(strict_types=1);

namespace Lemonade\Framework\Security;

use Lemonade\Framework\Clock\ClockInterface;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Security\Csrf\CsrfMiddleware;
use Lemonade\Framework\Security\Csrf\CsrfTokenManager;
use Lemonade\Framework\Security\Csrf\CsrfViewHelper;
use Lemonade\Framework\Security\Totp\TotpVerifier;

/**
 * Registers framework security mechanisms while leaving authentication policy and identity ownership to applications.
 */
final class SecurityServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilderInterface $container): void
    {
        $container->singleton(CsrfTokenManager::class, CsrfTokenManager::class);
        $container->singleton(CsrfMiddleware::class, CsrfMiddleware::class);
        $container->singleton(CsrfViewHelper::class, CsrfViewHelper::class);
        $container->singleton(TotpVerifier::class, static function (ContainerInterface $container): TotpVerifier {
            return new TotpVerifier(
                $container->get(ClockInterface::class),
            );
        });
    }
}
