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
    /**
     * Registers canonical CSRF services and the framework TOTP verifier.
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $container->scoped(CsrfTokenManager::class, CsrfTokenManager::class);
        $container->scoped(CsrfMiddleware::class, CsrfMiddleware::class);
        $container->scoped(CsrfViewHelper::class, CsrfViewHelper::class);
        $container->singleton(TotpVerifier::class, static function (ContainerInterface $container): TotpVerifier {
            return new TotpVerifier(
                $container->get(ClockInterface::class),
            );
        });
    }
}
