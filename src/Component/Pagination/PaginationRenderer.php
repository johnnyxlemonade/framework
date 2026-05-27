<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Pagination;

use Lemonade\Framework\Localization\TranslatorInterface;

final class PaginationRenderer
{
    /**
     * @param array<string, string> $classes
     */
    public function __construct(
        private readonly array $classes = [],
        private readonly ?TranslatorInterface $translator = null,
        private readonly int $visiblePages = 5,
        private readonly bool $showFirstLast = true,
    ) {}

    public function render(?PaginationState $state): string
    {
        if (!$state instanceof PaginationState || !$state->hasPages()) {
            return '';
        }

        $ulClass = $this->classes['ul'] ?? 'pagination mb-0';
        $liClass = $this->classes['li'] ?? 'page-item{active}{disabled}';
        $aClass = $this->classes['a'] ?? 'page-link';
        $spanClass = $this->classes['span'] ?? 'page-link';

        $html = '<nav aria-label="Pagination"><ul class="' . htmlspecialchars($ulClass, ENT_QUOTES, 'UTF-8') . '">';

        if ($this->showFirstLast) {
            $html .= $this->renderItem(
                $state->url(1),
                $this->translate('pagination.first', 'First'),
                $state->currentPage() === 1,
                false,
                $liClass,
                $aClass,
                $spanClass,
            );
        }

        $html .= $this->renderItem(
            $state->url($state->prevPage()),
            $this->translate('pagination.prev', 'Prev'),
            $state->currentPage() === 1,
            false,
            $liClass,
            $aClass,
            $spanClass,
        );

        $lastPrinted = 0;
        foreach ($state->pages($this->visiblePages) as $page) {
            if ($lastPrinted > 0 && $page - $lastPrinted > 1) {
                $html .= $this->renderGap($liClass, $spanClass);
            }

            $active = $page === $state->currentPage();
            $html .= $this->renderItem(
                $state->url($page),
                (string) $page,
                false,
                $active,
                $liClass,
                $aClass,
                $spanClass,
            );
            $lastPrinted = $page;
        }

        $html .= $this->renderItem(
            $state->url($state->nextPage()),
            $this->translate('pagination.next', 'Next'),
            !$state->hasNext(),
            false,
            $liClass,
            $aClass,
            $spanClass,
        );

        if ($this->showFirstLast) {
            $html .= $this->renderItem(
                $state->url($state->lastPage()),
                $this->translate('pagination.last', 'Last'),
                $state->currentPage() === $state->lastPage(),
                false,
                $liClass,
                $aClass,
                $spanClass,
            );
        }

        $html .= '</ul></nav>';

        return $html;
    }

    private function renderGap(string $liClass, string $spanClass): string
    {
        $li = str_replace(['{active}', '{disabled}'], ['', ' disabled'], $liClass);

        return '<li class="' . htmlspecialchars(trim($li), ENT_QUOTES, 'UTF-8')
            . '"><span class="' . htmlspecialchars($spanClass, ENT_QUOTES, 'UTF-8')
            . '">…</span></li>';
    }

    private function renderItem(
        string $url,
        string $label,
        bool $disabled,
        bool $active,
        string $liTemplate,
        string $aClass,
        string $spanClass,
    ): string {
        $li = str_replace(
            ['{active}', '{disabled}'],
            [$active ? ' active' : '', $disabled ? ' disabled' : ''],
            $liTemplate,
        );

        $labelEscaped = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        $liEscaped = htmlspecialchars(trim($li), ENT_QUOTES, 'UTF-8');

        if ($disabled || $active) {
            return '<li class="' . $liEscaped . '"><span class="'
                . htmlspecialchars($spanClass, ENT_QUOTES, 'UTF-8')
                . '">' . $labelEscaped . '</span></li>';
        }

        return '<li class="' . $liEscaped . '"><a class="'
            . htmlspecialchars($aClass, ENT_QUOTES, 'UTF-8')
            . '" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8')
            . '">' . $labelEscaped . '</a></li>';
    }

    private function translate(string $key, string $fallback): string
    {
        if ($this->translator === null) {
            return $fallback;
        }

        $line = $this->translator->get($key);

        return $line !== $key ? $line : $fallback;
    }
}
