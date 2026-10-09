<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Meta\Config;

use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;

/**
 * Collects metadata defaults contributed by an application or module.
 */
final class MetaConfigDefinition extends AbstractConfigDefinition
{
    /**
     * Starts an empty metadata-default definition.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Identifies the configuration section handled by this definition.
     */
    public static function moduleKey(): string
    {
        return 'meta';
    }

    /**
     * Sets the fallback website name appended to page titles.
     */
    public function websiteName(string $websiteName): self
    {
        return $this->set('website_name', $websiteName);
    }

    /**
     * Sets the fallback character encoding emitted for a page.
     */
    public function charset(string $charset): self
    {
        return $this->set('charset', $charset);
    }

    /**
     * Sets the fallback viewport declaration emitted for a page.
     */
    public function viewport(string $viewport): self
    {
        return $this->set('viewport', $viewport);
    }

    /**
     * Sets the fallback content rating emitted for a page.
     */
    public function rating(string $rating): self
    {
        return $this->set('rating', $rating);
    }

    /**
     * Sets the fallback separator between a page title and website name.
     */
    public function titleSeparator(string $titleSeparator): self
    {
        return $this->set('title_separator', $titleSeparator);
    }
}
