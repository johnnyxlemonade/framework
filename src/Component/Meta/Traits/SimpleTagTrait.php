<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Meta\Traits;

use function htmlspecialchars;

trait SimpleTagTrait
{
    private function renderSimpleTag(string $template, ?string $content): string
    {
        if ($content === null || $content === '') {
            return '';
        }

        return sprintf(
            $template,
            htmlspecialchars($content, ENT_QUOTES),
        );
    }
}
