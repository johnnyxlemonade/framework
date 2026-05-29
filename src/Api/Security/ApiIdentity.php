<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Security;

final class ApiIdentity
{
    /**
     * @param list<string> $scopes
     */
    public function __construct(
        private readonly string $id,
        private readonly string $type,
        private readonly array $scopes = [],
        private readonly ?string $name = null,
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function type(): string
    {
        return $this->type;
    }

    /**
     * @return list<string>
     */
    public function scopes(): array
    {
        return $this->scopes;
    }

    public function name(): ?string
    {
        return $this->name;
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }
}
