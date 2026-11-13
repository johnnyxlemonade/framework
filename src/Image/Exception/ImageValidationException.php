<?php

declare(strict_types=1);

namespace Lemonade\Framework\Image\Exception;

/** Signals that image input, geometry, or encoder options violate a documented invariant. */
final class ImageValidationException extends ImageException
{
    public const int DIMENSIONS_EXCEEDED = 1;

    public const int DECODE_MEMORY_EXCEEDED = 2;

    /**
     * Carries validation values for localized feedback without duplicating processor limits
     *
     * @param array<string,int> $parameters
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        public readonly array $parameters = [],
    ) {
        parent::__construct($message, $code, $previous);
    }
}
