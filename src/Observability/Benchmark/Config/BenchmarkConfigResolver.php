<?php

declare(strict_types=1);

namespace Lemonade\Framework\Observability\Benchmark\Config;

final class BenchmarkConfigResolver
{
    public function resolve(BenchmarkConfigDefinition ...$definitions): BenchmarkConfig
    {
        $injectHtmlComment = true;

        foreach ($definitions as $definition) {
            $data = $definition->toArray();

            if (array_key_exists('inject_html_comment', $data)) {
                $injectHtmlComment = $this->toBool($data['inject_html_comment'], $injectHtmlComment);
            }
        }

        return new BenchmarkConfig($injectHtmlComment);
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
