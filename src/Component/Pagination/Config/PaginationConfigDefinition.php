<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Pagination\Config;

use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;

/**
 * Collects pagination defaults contributed by an application or module.
 */
final class PaginationConfigDefinition extends AbstractConfigDefinition
{
    /**
     * Starts an empty pagination configuration definition.
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
        return 'pagination';
    }

    /**
     * Sets the page size used when a caller does not provide one.
     */
    public function defaultPerPage(int $defaultPerPage): self
    {
        return $this->set('default_per_page', $defaultPerPage);
    }

    /**
     * Sets the largest page size accepted from a caller.
     */
    public function maxPerPage(int $maxPerPage): self
    {
        return $this->set('max_per_page', $maxPerPage);
    }

    /**
     * Sets the number of page links displayed by the default renderer.
     */
    public function visiblePages(int $visiblePages): self
    {
        return $this->set('visible_pages', $visiblePages);
    }

    /**
     * Enables or disables first and last page links in the default renderer.
     */
    public function showFirstLast(bool $showFirstLast = true): self
    {
        return $this->set('show_first_last', $showFirstLast);
    }

    /**
     * Sets renderer class overrides keyed by the renderer's supported elements.
     *
     * @param array<string, string> $classes
     */
    public function classes(array $classes): self
    {
        return $this->set('classes', $classes);
    }
}
