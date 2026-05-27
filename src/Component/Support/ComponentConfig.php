<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Support;

final class ComponentConfig
{
    /**
     * @param array<mixed> $classes
     * @return array<string, string>
     */
    public static function normalizeClasses(array $classes): array
    {
        $normalized = [];
        foreach ($classes as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }
}
