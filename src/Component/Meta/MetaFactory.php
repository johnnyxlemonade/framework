<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Meta;

use Lemonade\Framework\Component\Meta\Sections\Dc;
use Lemonade\Framework\Component\Meta\Sections\Facebook;
use Lemonade\Framework\Component\Meta\Sections\Meta;
use Lemonade\Framework\Component\Meta\Sections\MetaEntityInterface;
use Lemonade\Framework\Component\Meta\Sections\Twitter;
use Stringable;

/**
 * Builds the HTML metadata block from ordered, replaceable metadata sections.
 */
final class MetaFactory implements Stringable
{
    /**
     * @var array<int, array<class-string<MetaEntityInterface>, MetaEntityInterface>>
     */
    private array $entities = [];

    /**
     * Initializes the standard metadata sections for the supplied page metadata.
     */
    public function __construct(
        protected readonly MetaData $data,
    ) {
        $this
            ->addEntity(new Meta($this->data), 10)
            ->addEntity(new Dc($this->data), 20)
            ->addEntity(new Facebook($this->data), 30)
            ->addEntity(new Twitter($this->data), 40);
    }

    /**
     * Adds or replaces a section at its rendering priority.
     */
    public function addEntity(MetaEntityInterface $entity, int $priority = 0): self
    {
        $this->entities[$priority][get_class($entity)] = $entity;
        return $this;
    }

    /**
     * Removes the first registered section of the supplied implementation class.
     */
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

    /**
     * Renders all registered sections in ascending priority order.
     */
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

    /**
     * Returns the rendered metadata HTML for string contexts.
     */
    public function __toString(): string
    {
        return $this->toHtml();
    }
}
