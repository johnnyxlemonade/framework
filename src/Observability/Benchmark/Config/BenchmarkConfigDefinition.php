<?php

declare(strict_types=1);

namespace Lemonade\Framework\Observability\Benchmark\Config;

use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;

final class BenchmarkConfigDefinition extends AbstractConfigDefinition
{
    public static function create(): self
    {
        return new self();
    }

    public static function moduleKey(): string
    {
        return 'benchmark';
    }

    public function injectHtmlComment(bool $enabled = true): self
    {
        return $this->set('inject_html_comment', $enabled);
    }
}
