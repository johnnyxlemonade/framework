<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Config;

use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;

final class ErrorConfigDefinition extends AbstractConfigDefinition
{
    public static function create(): self
    {
        return new self();
    }

    public static function moduleKey(): string
    {
        return 'error';
    }

    public function notFoundView(string $template): self
    {
        return $this->set('views.not_found', $template);
    }

    public function internalServerErrorView(string $template): self
    {
        return $this->set('views.internal_server_error', $template);
    }
}
