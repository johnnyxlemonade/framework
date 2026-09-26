<?php

declare(strict_types=1);

namespace Lemonade\Framework\Container;

use Closure;

final readonly class ContextualBindingValue
{
    /** @param 'service'|'factory'|'value' $kind */
    private function __construct(
        public string $kind,
        public mixed $value,
    ) {}

    public static function service(string $id): self
    {
        return new self('service', $id);
    }

    /** @param Closure(ContainerInterface):mixed $factory */
    public static function factory(Closure $factory): self
    {
        return new self('factory', $factory);
    }

    public static function value(mixed $value): self
    {
        return new self('value', $value);
    }
}
