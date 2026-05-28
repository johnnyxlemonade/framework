<?php

declare(strict_types=1);

namespace Lemonade\Framework\Debug\Dump\Renderer;

use Lemonade\Framework\Debug\Dump\Context\DumpContext;
use Lemonade\Framework\Debug\Dump\Contract\DumpRendererInterface;
use Lemonade\Framework\Debug\Dump\DumpOptions;
use Lemonade\Framework\Debug\Dump\Model\Dump;
use Lemonade\Framework\Debug\Dump\Model\DumpNode;

final class HtmlDumpRenderer implements DumpRendererInterface
{
    private bool $stylesRendered = false;

    public function __construct(
        private readonly DumpOptions $options,
    ) {}

    public function supports(DumpContext $context): bool
    {
        return !$context->isCli();
    }

    public function render(Dump $dump): string
    {
        $html = [];

        if ($this->shouldRenderStyles()) {
            $html[] = $this->renderStyles();
            $this->stylesRendered = true;
        }

        $html[] = '<fieldset class="dump lemonade-dump">';
        $html[] = '<legend>' . $this->escape($dump->context()->sourceLocation()->toString()) . '</legend>';
        $html[] = '<pre>';

        foreach ($dump->items() as $item) {
            $html[] = '<strong class="dump-heading">Debug #' . $item->index() . ' of ' . $dump->count() . '</strong>:';
            $html[] = $this->renderNode($item->value(), 0);
            $html[] = '';
        }

        $html[] = '</pre>';
        $html[] = '</fieldset>';

        return implode(PHP_EOL, $html) . PHP_EOL;
    }

    private function shouldRenderStyles(): bool
    {
        return $this->options->includeHtmlStyles() && !$this->stylesRendered;
    }

    private function renderStyles(): string
    {
        return <<<'HTML'
<style>
.lemonade-dump {
    display: block;
    margin: 16px 0;
    padding: 0;
    border: 1px solid #d0d7de;
    border-radius: 8px;
    background: #f6f8fa;
    color: #24292f;
    font: 13px/1.45 ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    overflow: hidden;
}

.lemonade-dump legend {
    margin-left: 12px;
    padding: 4px 8px;
    border: 1px solid #d0d7de;
    border-radius: 6px;
    background: #ffffff;
    color: #57606a;
    font-weight: 600;
    font-size: 12px;
}

.lemonade-dump pre {
    margin: 0;
    padding: 12px 14px;
    white-space: pre-wrap;
    word-break: break-word;
    overflow-x: auto;
}

.lemonade-dump .dump-heading {
    color: #0969da;
}

.lemonade-dump .dump-label {
    color: #8250df;
    font-weight: 600;
}

.lemonade-dump .dump-type {
    color: #57606a;
}

.lemonade-dump .dump-value {
    color: #116329;
}

.lemonade-dump .dump-flag {
    color: #9a6700;
    font-weight: 600;
}
</style>
HTML;
    }

    private function renderNode(DumpNode $node, int $level): string
    {
        $indent = str_repeat('  ', $level);
        $line = $indent
            . '<span class="dump-label">' . $this->escape($node->label()) . '</span>'
            . ' '
            . '<span class="dump-type">&lt;' . $this->escape($node->type()) . '&gt;</span>';

        if ($node->value() !== null) {
            $line .= ': <span class="dump-value">' . $this->escape($node->value()) . '</span>';
        }

        if ($node->isCircular()) {
            $line .= ' <span class="dump-flag">[circular]</span>';
        } elseif ($node->isTruncated()) {
            $line .= ' <span class="dump-flag">[truncated]</span>';
        }

        if (!$node->hasChildren()) {
            return $line;
        }

        $lines = [$line];

        foreach ($node->children() as $child) {
            $lines[] = $this->renderNode($child, $level + 1);
        }

        return implode(PHP_EOL, $lines);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
