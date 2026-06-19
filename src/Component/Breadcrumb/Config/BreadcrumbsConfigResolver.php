<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Breadcrumb\Config;

final class BreadcrumbsConfigResolver
{
    public function resolve(BreadcrumbsConfigDefinition ...$definitions): BreadcrumbsConfig
    {
        $frontendRootLabel = 'Domu';
        $frontendRootUrl = '/';
        $adminRootLabel = 'Admin';
        $adminRootUrl = '/admin';
        $classes = [];

        foreach ($definitions as $definition) {
            $data = $definition->toArray();
            $frontend = is_array($data['frontend'] ?? null) ? $data['frontend'] : [];
            $admin = is_array($data['admin'] ?? null) ? $data['admin'] : [];

            if (array_key_exists('root_label', $frontend)) {
                $frontendRootLabel = $this->stringOr($frontend['root_label'], $frontendRootLabel);
            }
            if (array_key_exists('root_url', $frontend)) {
                $frontendRootUrl = $this->stringOr($frontend['root_url'], $frontendRootUrl);
            }
            if (array_key_exists('root_label', $admin)) {
                $adminRootLabel = $this->stringOr($admin['root_label'], $adminRootLabel);
            }
            if (array_key_exists('root_url', $admin)) {
                $adminRootUrl = $this->stringOr($admin['root_url'], $adminRootUrl);
            }
            if (array_key_exists('classes', $data) && is_array($data['classes'])) {
                $classes = $this->normalizeClasses($data['classes']);
            }
        }

        return new BreadcrumbsConfig($frontendRootLabel, $frontendRootUrl, $adminRootLabel, $adminRootUrl, $classes);
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

    private function stringOr(mixed $value, string $default): string
    {
        if (!is_scalar($value)) {
            return $default;
        }
        $normalized = trim((string) $value);

        return $normalized === '' ? $default : $normalized;
    }
}
