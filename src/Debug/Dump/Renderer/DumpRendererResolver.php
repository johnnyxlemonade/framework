<?php

declare(strict_types=1);

namespace Lemonade\Framework\Debug\Dump\Renderer;

use Lemonade\Framework\Debug\Dump\Context\DumpContext;
use Lemonade\Framework\Debug\Dump\Contract\DumpRendererInterface;
use RuntimeException;

final class DumpRendererResolver
{
    /**
     * @param list<DumpRendererInterface> $renderers
     */
    public function __construct(
        private readonly array $renderers,
    ) {}

    public function resolve(DumpContext $context): DumpRendererInterface
    {
        foreach ($this->renderers as $renderer) {
            if ($renderer->supports($context)) {
                return $renderer;
            }
        }

        throw new RuntimeException('No dump renderer is available for current context.');
    }
}
