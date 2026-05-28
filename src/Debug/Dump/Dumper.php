<?php

declare(strict_types=1);

namespace Lemonade\Framework\Debug\Dump;

use Lemonade\Framework\Debug\Dump\Context\DumpContextFactory;
use Lemonade\Framework\Debug\Dump\Contract\DumperInterface;
use Lemonade\Framework\Debug\Dump\Contract\DumpOutputInterface;
use Lemonade\Framework\Debug\Dump\Contract\ValueInspectorInterface;
use Lemonade\Framework\Debug\Dump\Model\Dump;
use Lemonade\Framework\Debug\Dump\Model\DumpItem;
use Lemonade\Framework\Debug\Dump\Renderer\DumpRendererResolver;

final class Dumper implements DumperInterface
{
    public function __construct(
        private readonly DumpContextFactory $contextFactory,
        private readonly ValueInspectorInterface $inspector,
        private readonly DumpRendererResolver $rendererResolver,
        private readonly DumpOutputInterface $output,
        private readonly DumpOptions $options,
    ) {}

    public function dump(mixed ...$values): void
    {
        $this->output->write($this->render(...$values));
    }

    public function render(mixed ...$values): string
    {
        $context = $this->contextFactory->create();
        $renderer = $this->rendererResolver->resolve($context);

        return $renderer->render(
            new Dump(
                context: $context,
                items: $this->createItems($values),
            ),
        );
    }

    public function dd(mixed ...$values): never
    {
        $this->dump(...$values);

        exit(1);
    }

    /**
     * @param array<int|string, mixed> $values
     * @return list<DumpItem>
     */
    private function createItems(array $values): array
    {
        $items = [];
        $index = 1;

        foreach ($values as $value) {
            $items[] = new DumpItem(
                index: $index,
                value: $this->inspector->inspect($value, $this->options),
            );

            $index++;
        }

        return $items;
    }
}
