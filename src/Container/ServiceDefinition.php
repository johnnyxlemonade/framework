<?php

declare(strict_types=1);

namespace Lemonade\Framework\Container;

use Lemonade\Framework\Container\Definition\DefinitionTarget;

final readonly class ServiceDefinition
{
    /**
     * @param class-string|string $id
     * @param list<string> $tags
     * @param list<ServiceDecorator> $decorators
     */
    public function __construct(
        public string $id,
        public ServiceLifetime $lifetime,
        public DefinitionTarget $target,
        public array $tags = [],
        public array $decorators = [],
    ) {}

    public function withTag(string $tag): self
    {
        return new self(
            id: $this->id,
            lifetime: $this->lifetime,
            target: $this->target,
            tags: [...$this->tags, $tag],
            decorators: $this->decorators,
        );
    }

    public function withDecorator(ServiceDecorator $decorator): self
    {
        return new self(
            id: $this->id,
            lifetime: $this->lifetime,
            target: $this->target,
            tags: $this->tags,
            decorators: [...$this->decorators, $decorator],
        );
    }
}
