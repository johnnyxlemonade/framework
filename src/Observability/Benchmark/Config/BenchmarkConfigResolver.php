<?php

declare(strict_types=1);

namespace Lemonade\Framework\Observability\Benchmark\Config;

use Lemonade\Framework\Core\Config\Internal\ConfigValueNormalizer;

/**
 * Resolves immutable benchmark response settings from ordered definitions.
 */
final readonly class BenchmarkConfigResolver
{
    /**
     * Applies later definitions over framework defaults without retaining resolver state.
     */
    public function resolve(BenchmarkConfigDefinition ...$definitions): BenchmarkConfig
    {
        $injectHtmlComment = true;

        foreach ($definitions as $definition) {
            $data = $definition->toArray();

            if (array_key_exists('inject_html_comment', $data)) {
                $injectHtmlComment = ConfigValueNormalizer::boolOr(
                    $data['inject_html_comment'],
                    $injectHtmlComment,
                );
            }
        }

        return new BenchmarkConfig($injectHtmlComment);
    }
}
