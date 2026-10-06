<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload\Config;

use InvalidArgumentException;
use Lemonade\Framework\Mime\MimeTypeCatalog;
use Lemonade\Framework\Support\ByteSizeParser;

/**
 * Converts typed upload definitions into fail-fast runtime policies consumed by UploadFactory.
 *
 * It normalizes the canonical extension allowlist against MimeTypeCatalog and parses byte limits before a request
 * can reach an uploader.
 */
final readonly class UploadConfigResolver
{
    private MimeTypeCatalog $catalog;

    /**
     * Creates a resolver using the composed MIME catalog, or the framework catalog for isolated use.
     *
     * The parser is shared so all supported size syntax has one binary-byte interpretation.
     */
    public function __construct(
        ?MimeTypeCatalog $catalog = null,
        private ByteSizeParser $byteSizeParser = new ByteSizeParser(),
    ) {
        $this->catalog = $catalog ?? MimeTypeCatalog::default();
    }

    /**
     * Merges layered definitions into profiles with normalized extensions and positive byte limits.
     *
     * Unknown extensions, legacy MIME policies, and malformed limits are rejected before upload services are used.
     *
     * @throws InvalidArgumentException When an upload profile cannot form a safe catalog-backed policy
     */
    public function resolve(UploadConfigDefinition ...$definitions): UploadConfig
    {
        $files = [];
        $images = [];

        foreach ($definitions as $definition) {
            $data = $definition->toArray();

            $files = $this->mergeFileProfiles($files, $this->profileMap($data['files'] ?? null, 'files'));
            $images = $this->mergeImageProfiles($images, $this->profileMap($data['images'] ?? null, 'images'));
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
                throw new InvalidArgumentException('Upload file profiles must map non-empty names to profile mappings.');
            }

            $profileName = trim($name);
            $this->rejectLegacyMimePolicy($profile, $profileName);

            $current[$profileName] = new FileUploadProfileConfig(
                targetDirectory: $this->stringOr($profile['target_directory'] ?? '', ''),
                maxBytes: $this->maxBytes($profile['max_bytes'] ?? 10_485_760, $profileName),
                allowedExtensions: $this->allowedExtensions($profile['allowed_extensions'] ?? [], $profileName),
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
                throw new InvalidArgumentException('Upload image profiles must map non-empty names to profile mappings.');
            }

            $profileName = trim($name);
            $this->rejectLegacyMimePolicy($profile, $profileName);

            $current[$profileName] = new ImageUploadProfileConfig(
                targetDirectory: $this->stringOr($profile['target_directory'] ?? '', ''),
                maxBytes: $this->maxBytes($profile['max_bytes'] ?? 5_242_880, $profileName),
                allowedExtensions: $this->allowedExtensions($profile['allowed_extensions'] ?? ['jpg', 'jpeg', 'png', 'webp'], $profileName),
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
    private function profileMap(mixed $value, string $kind): array
    {
        if ($value === null) {
            return [];
        }

        if (!is_array($value)) {
            throw new InvalidArgumentException(sprintf('Upload %s profiles must be a mapping.', $kind));
        }

        $profiles = [];

        foreach ($value as $name => $profile) {
            if (!is_string($name)) {
                throw new InvalidArgumentException(sprintf('Upload %s profile names must be strings.', $kind));
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

    private function maxBytes(mixed $value, string $profile): int
    {
        try {
            return $this->byteSizeParser->parse($value);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidArgumentException(sprintf(
                'Upload profile "%s": invalid max_bytes: %s',
                $profile,
                $exception->getMessage(),
            ), previous: $exception);
        }
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

    /** @return list<string> */
    private function allowedExtensions(mixed $value, string $profile): array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException(sprintf(
                'Upload profile "%s": allowed_extensions must be a list of extensions.',
                $profile,
            ));
        }

        $items = [];

        foreach ($value as $item) {
            if (!is_string($item)) {
                throw new InvalidArgumentException(sprintf(
                    'Upload profile "%s": allowed_extensions must contain only strings.',
                    $profile,
                ));
            }

            try {
                $normalized = MimeTypeCatalog::normalizeExtension($item);
            } catch (InvalidArgumentException $exception) {
                throw new InvalidArgumentException(sprintf(
                    'Upload profile "%s": unknown allowed extension "%s".',
                    $profile,
                    $item,
                ), previous: $exception);
            }

            if ($this->catalog->definitionForExtension($normalized) === null) {
                throw new InvalidArgumentException(sprintf(
                    'Upload profile "%s": unknown allowed extension "%s".',
                    $profile,
                    $item,
                ));
            }

            if (in_array($normalized, $items, true)) {
                continue;
            }

            $items[] = $normalized;
        }

        return $items;
    }

    /** @param array<mixed> $profile */
    private function rejectLegacyMimePolicy(array $profile, string $profileName): void
    {
        if (!array_key_exists('allowed_mime_types', $profile)) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'Upload profile "%s": allowed_mime_types is no longer supported; use allowed_extensions.',
            $profileName,
        ));
    }
}
