<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Config;

use Lemonade\Framework\Core\Config\Internal\ConfigValueNormalizer;

/**
 * Merges ordered error-page definitions into normalized runtime template names.
 */
final readonly class ErrorConfigResolver
{
    /**
     * Resolves later non-empty template values over earlier values and defaults.
     */
    public function resolve(ErrorConfigDefinition ...$definitions): ErrorConfig
    {
        $notFoundView = 'errors/404';
        $internalServerErrorView = 'errors/500';

        foreach ($definitions as $definition) {
            $data = $definition->toArray();
            $views = is_array($data['views'] ?? null) ? $data['views'] : [];

            if (array_key_exists('not_found', $views)) {
                $notFoundView = ConfigValueNormalizer::nonEmptyStringOr($views['not_found'], $notFoundView);
            }

            if (array_key_exists('internal_server_error', $views)) {
                $internalServerErrorView = ConfigValueNormalizer::nonEmptyStringOr(
                    $views['internal_server_error'],
                    $internalServerErrorView,
                );
            }
        }

        return new ErrorConfig($notFoundView, $internalServerErrorView);
    }
}
