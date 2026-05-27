<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Meta\Traits;

use function htmlspecialchars;
use function sprintf;

trait HtmlAttributeTrait
{
    private function renderTagWithAttribute(string $template, string $name, ?string $content): string
    {
        if ($content === null || $content === '') {
            return '';
        }

        return sprintf(
            $template,
            htmlspecialchars($name, ENT_QUOTES),
            htmlspecialchars($content, ENT_QUOTES),
        );
    }
}
