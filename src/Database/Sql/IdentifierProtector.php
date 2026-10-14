<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Sql;

/**
 * Protects compound table and column references while preserving supported SQL aliases and expressions.
 */
final readonly class IdentifierProtector
{
    /**
     * Initializes this IdentifierProtector with the collaborators and state required by its database contract.
     */
    public function __construct(
        private IdentifierEscaperInterface $escaper,
    ) {
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function protect(
        string $item,
        bool $prefixSingle = false,
        ?bool $protectIdentifiers = null,
        bool $fieldExists = true,
    ): string {
        unset($prefixSingle, $protectIdentifiers, $fieldExists);

        if ($item === '*') {
            return $item;
        }

        if (str_contains($item, '(') || str_contains($item, "'")) {
            return $item;
        }

        if (preg_match('/\s+AS\s+/i', $item) === 1) {
            $parts = preg_split('/\s+AS\s+/i', $item, 2);
            if (!is_array($parts) || count($parts) !== 2) {
                return $item;
            }

            [$field, $alias] = $parts;

            return $this->protect($field) . ' AS ' . $this->escaper->identifier($alias);
        }

        if (str_contains($item, ' ')) {
            [$field, $alias] = explode(' ', $item, 2);

            return $this->protect($field) . ' ' . $this->escaper->identifier(trim($alias));
        }

        if (str_contains($item, '.')) {
            return implode('.', array_map(
                fn(string $part): string => $this->escaper->identifier($part),
                explode('.', $item),
            ));
        }

        return $this->escaper->table($item);
    }
}
