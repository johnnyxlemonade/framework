<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Config;

use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;

/**
 * Builds one composable layer of HTTP error-page template configuration.
 */
final class ErrorConfigDefinition extends AbstractConfigDefinition
{
    /**
     * Creates an empty definition that contributes only explicitly configured templates.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Returns the registry key under which error-page definitions are collected.
     */
    public static function moduleKey(): string
    {
        return 'error';
    }

    /**
     * Selects the view rendered for resources that cannot be found.
     */
    public function notFoundView(string $template): self
    {
        return $this->set('views.not_found', $template);
    }

    /**
     * Selects the view rendered for unexpected server failures.
     */
    public function internalServerErrorView(string $template): self
    {
        return $this->set('views.internal_server_error', $template);
    }
}
