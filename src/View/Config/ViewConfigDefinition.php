<?php

declare(strict_types=1);

namespace Lemonade\Framework\View\Config;

use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;

final class ViewConfigDefinition extends AbstractConfigDefinition
{
    public static function create(): self
    {
        return new self();
    }

    public static function moduleKey(): string
    {
        return 'view';
    }

    public function basePath(string $basePath): self
    {
        return $this->set('base_path', $basePath);
    }
}
