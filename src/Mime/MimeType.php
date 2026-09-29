<?php

declare(strict_types=1);

namespace Lemonade\Framework\Mime;

use InvalidArgumentException;

/**
 * Carries one normalized media type without client-supplied parameters.
 */
final readonly class MimeType
{
    private function __construct(private string $value)
    {
    }

    /**
     * Normalizes and validates the media type while discarding optional parameters
     */
    public static function fromString(string $value): self
    {
        $base = strtolower(trim(explode(';', $value, 2)[0]));

        if ($base === '' || preg_match('~^[a-z0-9!#$&^_.+-]+/[a-z0-9!#$&^_.+-]+$~', $base) !== 1) {
            throw new InvalidArgumentException('MIME type must be a valid type/subtype value.');
        }

        return new self(
            $base,
        );
    }

    /**
     * Returns the normalized type/subtype value used by catalog lookups
     */
    public function value(): string
    {
        return $this->value;
    }
}
