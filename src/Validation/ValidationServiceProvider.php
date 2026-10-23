<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation;

use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Http\Request\ResolvedRequestContext;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Validation\Endpoint\DefaultRecaptchaEndpointProvider;
use Lemonade\Framework\Validation\Endpoint\DefaultValidationEndpointProvider;
use Lemonade\Framework\Validation\Endpoint\DefaultVatValidationEndpointProvider;
use Lemonade\Framework\Validation\Endpoint\RecaptchaEndpointProviderInterface;
use Lemonade\Framework\Validation\Endpoint\ValidationEndpointProviderInterface;
use Lemonade\Framework\Validation\Endpoint\VatValidationEndpointProviderInterface;
use Lemonade\Framework\Validation\Rule\RecaptchaRule;
use Lemonade\Framework\Validation\Rule\RuleRegistry;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class ValidationServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilderInterface $container): void
    {
        $container->singleton(
            ValidationEndpointProviderInterface::class,
            DefaultValidationEndpointProvider::class,
        );
        $container->singleton(
            RecaptchaEndpointProviderInterface::class,
            DefaultRecaptchaEndpointProvider::class,
        );
        $container->singleton(
            VatValidationEndpointProviderInterface::class,
            DefaultVatValidationEndpointProvider::class,
        );
        $container->singleton(RuleRegistry::class, RuleRegistry::class);
        $container->singleton('validation.rules', RuleRegistry::class);
        $container->transient(ValidationRuleResolver::class, ValidationRuleResolver::class);

        foreach (RuleRegistry::builtInRules() as $ruleClass) {
            $container->transient($ruleClass, $ruleClass);
        }

        $container->transient(RecaptchaRule::class, static function (ContainerInterface $container): RecaptchaRule {
            $requestContext = $container->has(ResolvedRequestContext::class)
                ? $container->get(ResolvedRequestContext::class)
                : null;

            return new RecaptchaRule(
                client: $container->get(ClientInterface::class),
                requestFactory: $container->get(RequestFactoryInterface::class),
                streamFactory: $container->get(StreamFactoryInterface::class),
                endpointProvider: $container->get(RecaptchaEndpointProviderInterface::class),
                requestContext: $requestContext,
            );
        });

        $container->set(FormValidation::class, static function (ContainerInterface $container): FormValidation {
            return new FormValidation(
                translator: $container->get(TranslatorInterface::class),
                ruleResolver: $container->get(ValidationRuleResolver::class),
            );
        });

        $container->set('validator', FormValidation::class);
    }
}
