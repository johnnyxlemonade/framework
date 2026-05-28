<?php

declare(strict_types=1);

namespace Lemonade\Framework\Debug\Dump\Renderer;

use Lemonade\Framework\Debug\Dump\Context\DumpContext;
use Lemonade\Framework\Debug\Dump\Contract\DumpRendererInterface;
use Lemonade\Framework\Debug\Dump\Model\Dump;
use Lemonade\Framework\Debug\Dump\Model\DumpNode;

final class CliDumpRenderer implements DumpRendererInterface
{
    public function supports(DumpContext $context): bool
    {
        return $context->isCli();
    }

    public function render(Dump $dump): string
    {
        $lines = [];

        $lines[] = '';
        $lines[] = '--- dump: ' . $dump->context()->sourceLocation()->toString() . ' ---';

        foreach ($dump->items() as $item) {
            $lines[] = 'Debug #' . $item->index() . ' of ' . $dump->count() . ':';
            $lines[] = $this->renderNode($item->value(), 0);
        }

        $lines[] = '--- /dump ---';
        $lines[] = '';

        return implode(PHP_EOL, $lines);
    }

    private function renderNode(DumpNode $node, int $level): string
    {
        $indent = str_repeat('  ', $level);
        $line = $indent . $node->label() . ' <' . $node->type() . '>';

        if ($node->value() !== null) {
            $line .= ': ' . $node->value();
        }

        if ($node->isCircular()) {
            $line .= ' [circular]';
        } elseif ($node->isTruncated()) {
            $line .= ' [truncated]';
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
}
