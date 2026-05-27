<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Breadcrumb;

final class BreadcrumbRenderer
{
    /**
     * @param array<string, string> $classes
     */
    public function __construct(
        private readonly array $classes = [
            'ul' => 'breadcrumb-navigation',
            'li' => 'breadcrumb-link{active}',
            'a' => 'breadcrumb-link-anchor',
            'span' => 'breadcrumb-link-name',
        ],
    ) {}

    public function render(?BreadcrumbTrail $trail): string
    {
        if ($trail === null || $trail->count() === 0) {
            return '';
        }

        $items = $trail->items();
        $lastIndex = count($items) - 1;

        $ulClass = $this->classFor('ul');
        $liClassTemplate = $this->classFor('li');
        $aClass = $this->classFor('a');
        $spanClass = $this->classFor('span');

        $html = '<ul class="' . $this->escape($ulClass) . '" itemscope itemtype="https://schema.org/BreadcrumbList">' . PHP_EOL;

        foreach ($items as $index => $item) {
            $isActive = $item->active() || $index === $lastIndex;
            $activeClass = $isActive ? ' active' : '';
            $liClass = str_replace('{active}', $activeClass, $liClassTemplate);

            $name = $this->escape($item->label());
            $position = (string) ($index + 1);
            $url = $item->url();

            $html .= '    <li class="' . $this->escape($liClass) . '" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">' . PHP_EOL;

            if (is_string($url) && $url !== '') {
                $html .= '        <a href="' . $this->escape($url) . '" class="' . $this->escape($aClass) . '" itemprop="item" title="' . $name . '">' . PHP_EOL;
                $html .= '            <span class="' . $this->escape($spanClass) . '" itemprop="name">' . $name . '</span>' . PHP_EOL;
                $html .= '        </a>' . PHP_EOL;
            } else {
                $html .= '        <span class="' . $this->escape($spanClass) . '" itemprop="name">' . $name . '</span>' . PHP_EOL;
            }

            $html .= '        <meta itemprop="position" content="' . $position . '">' . PHP_EOL;
            $html .= '    </li>' . PHP_EOL;
        }

        $html .= '</ul>';

        return $html;
    }

    private function classFor(string $element): string
    {
        return $this->classes[$element] ?? '';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
