<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Security;

final readonly class ApiIdentity
{
    /**
     * @param non-empty-string $id
     * @param non-empty-string $type
     * @param list<non-empty-string> $scopes
     */
    public function __construct(
        private string $id,
        private string $type,
        private array $scopes = [],
        private ?string $name = null,
    ) {
        if (trim($id) === '') {
            throw new \InvalidArgumentException('API identity id cannot be empty.');
        }

        if (trim($type) === '') {
            throw new \InvalidArgumentException('API identity type cannot be empty.');
        }

        foreach ($scopes as $scope) {
            if ($scope === '') {
                throw new \InvalidArgumentException('API identity scope must not be empty.');
            }
        }
    }

    public function id(): string
    {
        return $this->id;
    }

    public function type(): string
    {
        return $this->type;
    }

    /**
     * @return list<non-empty-string>
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
