<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Validation\Rule\RuleRegistry;

final class ValidationServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(RuleRegistry::class, RuleRegistry::class);
        $container->singleton('validation.rules', RuleRegistry::class);
        $container->singleton(ValidationRuleResolver::class, ValidationRuleResolver::class);

        $container->set(FormValidation::class, static function (ContainerInterface $container): FormValidation {
            return new FormValidation(
                translator: $container->get(TranslatorInterface::class),
                ruleResolver: $container->get(ValidationRuleResolver::class),
            );
        });

        $container->set('validator', FormValidation::class);
    }
}
