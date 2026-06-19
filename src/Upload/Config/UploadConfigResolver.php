<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload\Config;

final class UploadConfigResolver
{
    public function resolve(UploadConfigDefinition ...$definitions): UploadConfig
    {
        $files = [];
        $images = [];

        foreach ($definitions as $definition) {
            $data = $definition->toArray();

            $files = $this->mergeFileProfiles($files, $this->profileMap($data['files'] ?? null));
            $images = $this->mergeImageProfiles($images, $this->profileMap($data['images'] ?? null));
        }

        return new UploadConfig($files, $images);
    }

    /**
     * @param array<string, FileUploadProfileConfig> $current
     * @param array<string, mixed> $rawProfiles
     * @return array<string, FileUploadProfileConfig>
     */
    private function mergeFileProfiles(array $current, array $rawProfiles): array
    {
        foreach ($rawProfiles as $name => $profile) {
            if (!is_string($name) || trim($name) === '' || !is_array($profile)) {
                continue;
            }

            $current[trim($name)] = new FileUploadProfileConfig(
                targetDirectory: $this->stringOr($profile['target_directory'] ?? '', ''),
                maxBytes: max(1, $this->intOr($profile['max_bytes'] ?? 10_485_760, 10_485_760)),
                allowedMimeTypes: $this->stringList($profile['allowed_mime_types'] ?? []),
                allowedExtensions: $this->stringList($profile['allowed_extensions'] ?? []),
            );
        }

        return $current;
    }

    /**
     * @param array<string, ImageUploadProfileConfig> $current
     * @param array<string, mixed> $rawProfiles
     * @return array<string, ImageUploadProfileConfig>
     */
    private function mergeImageProfiles(array $current, array $rawProfiles): array
    {
        foreach ($rawProfiles as $name => $profile) {
            if (!is_string($name) || trim($name) === '' || !is_array($profile)) {
                continue;
            }

            $current[trim($name)] = new ImageUploadProfileConfig(
                targetDirectory: $this->stringOr($profile['target_directory'] ?? '', ''),
                maxBytes: max(1, $this->intOr($profile['max_bytes'] ?? 5_242_880, 5_242_880)),
                allowedMimeTypes: $this->stringList($profile['allowed_mime_types'] ?? ['image/jpeg', 'image/png', 'image/webp']),
                allowedExtensions: $this->stringList($profile['allowed_extensions'] ?? ['jpg', 'jpeg', 'png', 'webp']),
                reencode: $this->toBool($profile['reencode'] ?? true, true),
                minWidth: $this->nullableInt($profile['min_width'] ?? null),
                maxWidth: $this->nullableInt($profile['max_width'] ?? null),
                minHeight: $this->nullableInt($profile['min_height'] ?? null),
                maxHeight: $this->nullableInt($profile['max_height'] ?? null),
            );
        }

        return $current;
    }

    /**
     * @return array<string, mixed>
     */
    private function profileMap(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $profiles = [];

        foreach ($value as $name => $profile) {
            if (!is_string($name)) {
                continue;
            }

            $profiles[$name] = $profile;
        }

        return $profiles;
    }

    private function stringOr(mixed $value, string $default): string
    {
        if (!is_scalar($value)) {
            return $default;
        }
        $normalized = trim((string) $value);

        return $normalized === '' ? $default : $normalized;
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

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return (int) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return null;
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

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $item) {
            if (!is_scalar($item)) {
                continue;
            }

            $normalized = trim((string) $item);
            if ($normalized === '' || in_array($normalized, $items, true)) {
                continue;
            }

            $items[] = $normalized;
        }

        return $items;
    }
}
