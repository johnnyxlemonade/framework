<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload\Resolver;

use Psr\Http\Message\UploadedFileInterface;

final class UploadedFileResolver
{
    /**
     * @param array<string, mixed> $files
     */
    public function resolve(array $files, string $inputName): ?UploadedFileInterface
    {
        if ($inputName === '') {
            return null;
        }

        if (isset($files[$inputName]) && $files[$inputName] instanceof UploadedFileInterface) {
            return $files[$inputName];
        }

        $segments = preg_split('/\[|\]/', $inputName);
        if ($segments === false) {
            return null;
        }

        $segments = array_values(array_filter(
            $segments,
            static fn(string $segment): bool => $segment !== '',
        ));

        if ($segments === []) {
            return null;
        }

        $current = $files;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }

            $current = $current[$segment];
        }

        return $current instanceof UploadedFileInterface ? $current : null;
    }
}
