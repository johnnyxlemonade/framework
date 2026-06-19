<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Pagination\Config;

final class PaginationConfigResolver
{
    public function resolve(PaginationConfigDefinition ...$definitions): PaginationConfig
    {
        $defaultPerPage = 20;
        $maxPerPage = 100;
        $visiblePages = 7;
        $showFirstLast = true;
        $classes = [];

        foreach ($definitions as $definition) {
            $data = $definition->toArray();
            if (array_key_exists('default_per_page', $data)) {
                $defaultPerPage = max(1, $this->intOr($data['default_per_page'], $defaultPerPage));
            }
            if (array_key_exists('max_per_page', $data)) {
                $maxPerPage = max(1, $this->intOr($data['max_per_page'], $maxPerPage));
            }
            if (array_key_exists('visible_pages', $data)) {
                $visiblePages = max(1, $this->intOr($data['visible_pages'], $visiblePages));
            }
            if (array_key_exists('show_first_last', $data)) {
                $showFirstLast = $this->toBool($data['show_first_last'], $showFirstLast);
            }
            if (array_key_exists('classes', $data) && is_array($data['classes'])) {
                $classes = $this->normalizeClasses($data['classes']);
            }
        }

        return new PaginationConfig($defaultPerPage, $maxPerPage, $visiblePages, $showFirstLast, $classes);
    }

    /**
     * @param array<mixed> $classes
     * @return array<string, string>
     */
    private function normalizeClasses(array $classes): array
    {
        $normalized = [];

        foreach ($classes as $key => $value) {
            if (!is_string($key) || !is_scalar($value)) {
                continue;
            }
            $key = trim($key);
            $value = trim((string) $value);
            if ($key === '' || $value === '') {
                continue;
            }
            $normalized[$key] = $value;
        }

        return $normalized;
    }

    private function intOr(mixed $value, int $default): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return (int) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }

    private function toBool(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (!is_scalar($value)) {
            return $default;
        }
        $resolved = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $resolved ?? $default;
    }
}
