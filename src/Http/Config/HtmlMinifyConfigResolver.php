<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Config;

final class HtmlMinifyConfigResolver
{
    public function resolve(HtmlMinifyConfigDefinition ...$definitions): HtmlMinifyConfig
    {
        $enabled = false;

        foreach ($definitions as $definition) {
            $data = $definition->toArray();

            if (array_key_exists('enabled', $data)) {
                $enabled = $this->toBool($data['enabled'], false);
            }
        }

        return new HtmlMinifyConfig($enabled);
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
