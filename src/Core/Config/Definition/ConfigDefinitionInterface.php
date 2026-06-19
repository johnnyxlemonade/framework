<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Config\Definition;

interface ConfigDefinitionInterface
{
    public static function moduleKey(): string;

    /**
     * @return array<mixed>
     */
    public function toArray(): array;
}
