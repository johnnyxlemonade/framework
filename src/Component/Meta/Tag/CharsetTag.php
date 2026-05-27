<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Meta\Tag;

use function htmlspecialchars;
use function sprintf;

final class CharsetTag implements TagInterface
{
    public function __construct(
        private readonly ?string $charset,
    ) {}

    public function render(): string
    {
        if ($this->charset === null || $this->charset === '') {
            return '';
        }

        return sprintf(
            '<meta charset="%s">',
            htmlspecialchars($this->charset, ENT_QUOTES),
        );
    }
}
