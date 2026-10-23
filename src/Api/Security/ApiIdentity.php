<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Security;

/**
 * Carries the authenticated API principal, its permissions and its verified trust boundary.
 *
 * The authenticator owns trust assignment. Identity type and scopes remain descriptive and never
 * promote an external identity to internal trust.
 */
final readonly class ApiIdentity
{
    /**
     * Creates an immutable identity from claims already verified by an API authenticator.
     *
     * Omitted trust defaults to external so existing callers cannot acquire internal access implicitly.
     *
     * @param non-empty-string $id
     * @param non-empty-string $type
     * @param list<non-empty-string> $scopes
     * @throws \InvalidArgumentException When the identifier, type or any scope is empty.
     */
    public function __construct(
        private string $id,
        private string $type,
        private array $scopes = [],
        private ?string $name = null,
        private ApiIdentityTrust $trust = ApiIdentityTrust::External,
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

    /**
     * Returns the stable principal identifier supplied by the authenticator.
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * Returns the authenticator-defined identity category without granting trust from its value.
     */
    public function type(): string
    {
        return $this->type;
    }

    /**
     * Returns the permissions verified for this identity.
     *
     * @return list<non-empty-string>
     */
    public function scopes(): array
    {
        return $this->scopes;
    }

    /**
     * Returns the optional human-readable principal name.
     */
    public function name(): ?string
    {
        return $this->name;
    }

    /**
     * Reports whether the identity owns the exact supplied permission.
     */
    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }

    /**
     * Returns the trust boundary established by the authenticator.
     */
    public function trust(): ApiIdentityTrust
    {
        return $this->trust;
    }
}
