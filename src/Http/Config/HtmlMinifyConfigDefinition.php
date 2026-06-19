<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Config;

use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;

final class HtmlMinifyConfigDefinition extends AbstractConfigDefinition
{
    public static function create(): self
    {
        return new self();
    }

    public static function moduleKey(): string
    {
        return 'html_minify';
    }

    public function enabled(bool $enabled = true): self
    {
        return $this->set('enabled', $enabled);
    }

    public function disabled(): self
    {
        return $this->enabled(false);
    }
}
