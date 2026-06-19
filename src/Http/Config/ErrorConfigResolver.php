<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Config;

final class ErrorConfigResolver
{
    public function resolve(ErrorConfigDefinition ...$definitions): ErrorConfig
    {
        $notFoundView = 'errors/404';
        $internalServerErrorView = 'errors/500';

        foreach ($definitions as $definition) {
            $data = $definition->toArray();
            $views = is_array($data['views'] ?? null) ? $data['views'] : [];

            if (array_key_exists('not_found', $views)) {
                $notFoundView = $this->stringOr($views['not_found'], $notFoundView);
            }

            if (array_key_exists('internal_server_error', $views)) {
                $internalServerErrorView = $this->stringOr($views['internal_server_error'], $internalServerErrorView);
            }
        }

        return new ErrorConfig($notFoundView, $internalServerErrorView);
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
