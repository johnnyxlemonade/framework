<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Config;

use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;

/**
 * Builds one composable layer of HTML minification configuration.
 */
final class HtmlMinifyConfigDefinition extends AbstractConfigDefinition
{
    /**
     * Creates an empty definition that contributes only explicitly configured values.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Returns the registry key under which HTML minification definitions are collected.
     */
    public static function moduleKey(): string
    {
        return 'html_minify';
    }

    /**
     * Sets whether eligible HTML responses are minified.
     */
    public function enabled(bool $enabled = true): self
    {
        return $this->set('enabled', $enabled);
    }

    /**
     * Disables HTML response minification for this definition layer.
     */
    public function disabled(): self
    {
        return $this->enabled(false);
    }
}
