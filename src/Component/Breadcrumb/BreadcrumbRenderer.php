<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Breadcrumb;

/**
 * Renders generic breadcrumb trail data as escaped semantic BreadcrumbList markup.
 */
final class BreadcrumbRenderer
{
    /**
     * Renders the supplied trail with the final item as the active non-link item.
     */
    public function render(?BreadcrumbTrail $trail): string
    {
        if ($trail === null || $trail->count() === 0) {
            return '';
        }

        $items = $trail->items();
        $lastIndex = count($items) - 1;

        $html = '<ul class="breadcrumb mb-0" itemscope itemtype="https://schema.org/BreadcrumbList">' . PHP_EOL;

        foreach ($items as $index => $item) {
            $isActive = $index === $lastIndex;
            $liClass = $isActive ? 'breadcrumb-item active' : 'breadcrumb-item';

            $name = $this->escape($item->label());
            $position = (string) ($index + 1);
            $url = $item->url();

            $html .= '    <li class="' . $this->escape($liClass) . '" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">' . PHP_EOL;

            if (!$isActive && is_string($url) && $url !== '') {
                $html .= '        <a href="' . $this->escape($url) . '" class="text-decoration-none" itemprop="item" title="' . $name . '">' . PHP_EOL;
                $html .= '            <span itemprop="name">' . $name . '</span>' . PHP_EOL;
                $html .= '        </a>' . PHP_EOL;
            } else {
                $html .= '        <span itemprop="name"' . ($isActive ? ' aria-current="page"' : '') . '>' . $name . '</span>' . PHP_EOL;
            }

            $html .= '        <meta itemprop="position" content="' . $position . '">' . PHP_EOL;
            $html .= '    </li>' . PHP_EOL;
        }

        $html .= '</ul>';

        return $html;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
