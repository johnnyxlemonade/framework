<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Meta;

use Lemonade\Framework\Component\Meta\Sections\Dc;
use Lemonade\Framework\Component\Meta\Sections\Facebook;
use Lemonade\Framework\Component\Meta\Sections\Meta;
use Lemonade\Framework\Component\Meta\Sections\MetaEntityInterface;
use Lemonade\Framework\Component\Meta\Sections\Twitter;
use Stringable;

final class MetaFactory implements Stringable
{
    /** @var array<int, array<class-string<MetaEntityInterface>, MetaEntityInterface>> */
    private array $entities = [];

    public function __construct(
        protected readonly MetaData $data,
    ) {
        $this
            ->addEntity(new Meta($this->data), 10)
            ->addEntity(new Dc($this->data), 20)
            ->addEntity(new Facebook($this->data), 30)
            ->addEntity(new Twitter($this->data), 40);
    }

    public function addEntity(MetaEntityInterface $entity, int $priority = 0): self
    {
        $this->entities[$priority][get_class($entity)] = $entity;
        return $this;
    }

    public function removeEntity(string $entityClassName): self
    {
        foreach ($this->entities as $priority => $group) {
            if (isset($group[$entityClassName])) {
                unset($this->entities[$priority][$entityClassName]);
                return $this;
            }
        }
        return $this;
    }

    public function toHtml(): string
    {
        ksort($this->entities);

        return PHP_EOL
            . implode('', array_map(
                fn(MetaEntityInterface $entity) => $entity->render(),
                array_merge(...array_values($this->entities)),
            ))
            . PHP_EOL;
    }

    public function __toString(): string
    {
        return $this->toHtml();
    }
}
