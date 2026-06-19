<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Pagination\Config;

use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;

final class PaginationConfigDefinition extends AbstractConfigDefinition
{
    public static function create(): self
    {
        return new self();
    }

    public static function moduleKey(): string
    {
        return 'pagination';
    }

    public function defaultPerPage(int $defaultPerPage): self
    {
        return $this->set('default_per_page', $defaultPerPage);
    }

    public function maxPerPage(int $maxPerPage): self
    {
        return $this->set('max_per_page', $maxPerPage);
    }

    public function visiblePages(int $visiblePages): self
    {
        return $this->set('visible_pages', $visiblePages);
    }

    public function showFirstLast(bool $showFirstLast = true): self
    {
        return $this->set('show_first_last', $showFirstLast);
    }

    /**
     * @param array<string, string> $classes
     */
    public function classes(array $classes): self
    {
        return $this->set('classes', $classes);
    }
}
