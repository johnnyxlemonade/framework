<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Validation\Rule\RuleRegistry;
use Lemonade\Framework\Validation\Rule\ValidationRuleInterface;
use RuntimeException;

final class ValidationRuleResolver
{
    /** @var array<string, ValidationRuleInterface> */
    private array $resolved = [];

    public function __construct(
        private readonly RuleRegistry $registry,
        private readonly ContainerInterface $container,
    ) {}

    public function has(string $name): bool
    {
        return $this->registry->has($name);
    }

    public function resolve(string $name): ?ValidationRuleInterface
    {
        if (isset($this->resolved[$name])) {
            return $this->resolved[$name];
        }

        $registered = $this->registry->get($name);
        if ($registered === null) {
            return null;
        }

        if ($registered instanceof ValidationRuleInterface) {
            return $this->resolved[$name] = $registered;
        }

        $rule = $this->container->get($registered);
        if (!$rule instanceof ValidationRuleInterface) {
            throw new RuntimeException(sprintf(
                'Resolved validation rule "%s" (%s) must implement %s, got %s.',
                $name,
                $registered,
                ValidationRuleInterface::class,
                get_debug_type($rule),
            ));
        }

        return $this->resolved[$name] = $rule;
    }
}
