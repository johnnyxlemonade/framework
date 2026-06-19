<?php

declare(strict_types=1);

namespace Lemonade\Framework\View\Config;

final class ViewConfigResolver
{
    public function resolve(ViewConfigDefinition ...$definitions): ViewConfig
    {
        $basePath = 'app/Views';

        foreach ($definitions as $definition) {
            $data = $definition->toArray();

            if (array_key_exists('base_path', $data)) {
                $basePath = $this->stringOr($data['base_path'], $basePath);
            }
        }

        return new ViewConfig($basePath);
    }

    private function stringOr(mixed $value, string $default): string
    {
        if (!is_scalar($value)) {
            return $default;
        }

        return (string) $value;
    }
}
