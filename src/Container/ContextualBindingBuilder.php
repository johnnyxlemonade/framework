<?php

declare(strict_types=1);

namespace Lemonade\Framework\Container;

use Closure;
use Lemonade\Framework\Container\Exception\InvalidContextualBindingException;

final class ContextualBindingBuilder
{
    /** @var 'dependency'|'parameter'|null */
    private ?string $kind = null;
    private ?string $key = null;

    /** @param Closure():void|null $onChange */
    public function __construct(
        private readonly ContainerBuilder $builder,
        private readonly string $consumer,
        private readonly ?Closure $onChange = null,
    ) {}

    /** @param class-string|string $dependency */
    public function needs(string $dependency): self
    {
        $this->kind = 'dependency';
        $this->key = $dependency;

        return $this;
    }

    public function parameter(string $name): self
    {
        $this->builder->assertConstructorParameterExists($this->consumer, $name);
        $this->kind = 'parameter';
        $this->key = $name;

        return $this;
    }

    /** @param class-string|string|callable(ContainerInterface):mixed|object $value */
    public function give(string|callable|object $value): void
    {
        $this->assertTarget('dependency', 'give');

        $bindingValue = is_string($value)
            ? ContextualBindingValue::service($value)
            : ($value instanceof Closure || !is_object($value)
                ? ContextualBindingValue::factory(Closure::fromCallable($value))
                : ContextualBindingValue::value($value));

        $this->bind($bindingValue);
    }

    public function value(mixed $value): void
    {
        $this->assertTarget('parameter', 'value');
        $this->bind(ContextualBindingValue::value($value));
    }

    /** @param class-string|string $configServiceId */
    public function config(string $configServiceId): void
    {
        $this->assertTarget('parameter', 'config');
        $this->bind(ContextualBindingValue::service($configServiceId));
    }

    private function assertTarget(string $expectedKind, string $method): void
    {
        if ($this->kind !== $expectedKind || $this->key === null) {
            throw new InvalidContextualBindingException(sprintf(
                'Contextual binding for "%s" must call %s() before %s().',
                $this->consumer,
                $expectedKind === 'dependency' ? 'needs' : 'parameter',
                $method,
            ));
        }
    }

    private function bind(ContextualBindingValue $value): void
    {
        if ($this->kind === null || $this->key === null) {
            throw new InvalidContextualBindingException(sprintf(
                'Contextual binding for "%s" has no target.',
                $this->consumer,
            ));
        }

        $this->builder->addContextualBinding(new ContextualBinding(
            consumer: $this->consumer,
            kind: $this->kind,
            key: $this->key,
            value: $value,
        ));
        ($this->onChange)?->__invoke();
    }
}
