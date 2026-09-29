<?php

declare(strict_types=1);

namespace Lemonade\Framework\Mime;

use InvalidArgumentException;
use Lemonade\Framework\Mime\Exception\MimeTypeCatalogConflictException;

/**
 * Provides immutable extension and server-detected MIME indexes with unambiguous accepted values.
 */
final readonly class MimeTypeCatalog
{
    /**
     * Builds forward and reverse indexes once from curated definitions.
     *
     * @param list<MimeTypeDefinition> $definitions
     */
    public function __construct(array $definitions)
    {
        $byExtension = [];
        $byMime = [];
        $canonicalMimeValues = [];

        foreach ($definitions as $definition) {
            if (!$definition instanceof MimeTypeDefinition) {
                throw new InvalidArgumentException('MIME catalog definitions must be MimeTypeDefinition instances.');
            }

            foreach ($definition->extensions() as $extension) {
                if (isset($byExtension[$extension])) {
                    throw new MimeTypeCatalogConflictException(sprintf(
                        'Extension "%s" belongs to more than one MIME definition.',
                        $extension,
                    ));
                }

                $byExtension[$extension] = $definition;
            }

            $canonicalMime = $definition->canonicalMime();
            $canonicalValue = $canonicalMime->value();

            if (isset($canonicalMimeValues[$canonicalValue])) {
                throw new MimeTypeCatalogConflictException(sprintf(
                    'Canonical MIME value "%s" belongs to more than one definition.',
                    $canonicalValue,
                ));
            }

            if (isset($byMime[$canonicalValue])) {
                throw new MimeTypeCatalogConflictException(sprintf(
                    'Canonical MIME value "%s" collides with an accepted alias.',
                    $canonicalValue,
                ));
            }

            $canonicalMimeValues[$canonicalValue] = true;
            $byMime[$canonicalValue] = $definition;

            foreach ($definition->aliases() as $alias) {
                $aliasValue = $alias->value();

                if (isset($canonicalMimeValues[$aliasValue])) {
                    throw new MimeTypeCatalogConflictException(sprintf(
                        'Accepted alias "%s" collides with a canonical MIME value.',
                        $aliasValue,
                    ));
                }

                if (isset($byMime[$aliasValue])) {
                    throw new MimeTypeCatalogConflictException(sprintf(
                        'Accepted alias "%s" belongs to more than one MIME definition.',
                        $aliasValue,
                    ));
                }

                $byMime[$aliasValue] = $definition;
            }
        }

        ksort($byExtension);
        ksort($byMime);

        $this->byExtension = $byExtension;
        $this->byMime = $byMime;
    }

    /**
     * @var array<string, MimeTypeDefinition>
     */
    private array $byExtension;

    /**
     * @var array<string, MimeTypeDefinition>
     */
    private array $byMime;

    /**
     * Returns the framework's curated cross-media catalog
     */
    public static function default(): self
    {
        return self::fromDefaultAndAdditionalDefinitions([]);
    }

    /**
     * Creates an immutable catalog from curated framework definitions and validated application additions
     *
     * @param list<MimeTypeDefinition> $additionalDefinitions
     */
    public static function fromDefaultAndAdditionalDefinitions(array $additionalDefinitions): self
    {
        $definitions = [
            ...self::defaultDefinitions(),
            ...$additionalDefinitions,
        ];

        return new self(
            $definitions,
        );
    }

    /**
     * Returns the framework-owned definitions that precede every application contribution.
     *
     * @return list<MimeTypeDefinition>
     */
    private static function defaultDefinitions(): array
    {
        return [
            self::definition('text/plain', ['txt', 'md']),
            self::definition('text/csv', ['csv']),
            self::definition('application/json', ['json']),
            self::definition('application/xml', ['xml'], ['text/xml']),
            self::definition('application/yaml', ['yaml', 'yml']),
            self::definition('text/html', ['html', 'htm'], [], MimeRisk::ActiveContent),
            self::definition('text/css', ['css']),

            self::definition('application/pdf', ['pdf']),
            self::definition('application/rtf', ['rtf'], ['text/rtf']),
            self::definition('application/msword', ['doc']),
            self::definition(
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                ['docx'],
                [],
                MimeRisk::Container,
            ),
            self::definition('application/vnd.ms-excel', ['xls']),
            self::definition(
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ['xlsx'],
                [],
                MimeRisk::Container,
            ),
            self::definition('application/vnd.ms-powerpoint', ['ppt']),
            self::definition(
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                ['pptx'],
                [],
                MimeRisk::Container,
            ),
            self::definition('application/vnd.oasis.opendocument.text', ['odt'], [], MimeRisk::Container),
            self::definition('application/vnd.oasis.opendocument.spreadsheet', ['ods'], [], MimeRisk::Container),
            self::definition('application/vnd.oasis.opendocument.presentation', ['odp'], [], MimeRisk::Container),

            self::definition('application/zip', ['zip'], [], MimeRisk::Container),
            self::definition('application/gzip', ['gz', 'gzip'], ['application/x-gzip'], MimeRisk::Container),
            self::definition('application/x-tar', ['tar'], [], MimeRisk::Container),
            self::definition('application/x-7z-compressed', ['7z'], [], MimeRisk::Container),
            self::definition('application/vnd.rar', ['rar'], ['application/x-rar-compressed'], MimeRisk::Container),

            self::definition('image/jpeg', ['jpg', 'jpeg', 'jpe']),
            self::definition('image/png', ['png']),
            self::definition('image/gif', ['gif']),
            self::definition('image/webp', ['webp']),
            self::definition('image/tiff', ['tif', 'tiff']),
            self::definition('image/bmp', ['bmp']),
            self::definition('image/vnd.microsoft.icon', ['ico'], ['image/x-icon']),
            self::definition('image/svg+xml', ['svg'], [], MimeRisk::ActiveContent),

            self::definition('audio/mpeg', ['mp3']),
            self::definition('audio/wav', ['wav'], ['audio/x-wav']),
            self::definition('audio/ogg', ['ogg'], ['application/ogg']),
            self::definition('audio/mp4', ['m4a']),
            self::definition('video/mp4', ['mp4']),
            self::definition('video/webm', ['webm']),

            self::definition('application/x-pem-file', ['pem']),
            self::definition('application/pkix-cert', ['crt', 'cer', 'der']),
            self::definition('application/x-pkcs12', ['p12', 'pfx'], [], MimeRisk::Container),
            self::definition('application/pkcs7-mime', ['p7b', 'p7c'], [], MimeRisk::Container),

            self::definition('application/x-httpd-php', ['php', 'phtml'], [], MimeRisk::Executable),
            self::definition('application/x-httpd-php-source', ['phar'], [], MimeRisk::Executable),
            self::definition('text/javascript', ['js'], ['application/javascript'], MimeRisk::ActiveContent),
            self::definition('application/vnd.microsoft.portable-executable', ['exe', 'dll'], [], MimeRisk::Executable),
            self::definition('text/x-shellscript', ['sh', 'bash'], [], MimeRisk::Executable),
        ];
    }

    /**
     * Creates one curated catalog definition from validated literal data.
     *
     * @param list<string> $extensions
     * @param list<string> $aliases
     */
    private static function definition(
        string $mime,
        array $extensions,
        array $aliases = [],
        MimeRisk $risk = MimeRisk::Passive,
    ): MimeTypeDefinition {
        return new MimeTypeDefinition(
            MimeType::fromString($mime),
            $extensions,
            $aliases,
            $risk,
        );
    }

    /**
     * Normalizes a suffix with or without its leading dot
     */
    public static function normalizeExtension(string $extension): string
    {
        $normalized = strtolower(ltrim(trim($extension), '.'));

        if ($normalized === '' || preg_match('/^[a-z0-9][a-z0-9+-]*$/', $normalized) !== 1) {
            throw new InvalidArgumentException('Extension is invalid.');
        }

        return $normalized;
    }

    /**
     * Finds the format family assigned to an extension
     */
    public function definitionForExtension(string $extension): ?MimeTypeDefinition
    {
        $normalizedExtension = self::normalizeExtension($extension);

        return $this->byExtension[$normalizedExtension] ?? null;
    }

    /**
     * Returns the preferred MIME value assigned to an extension
     */
    public function canonicalMimeForExtension(string $extension): ?MimeType
    {
        return $this->definitionForExtension($extension)?->canonicalMime();
    }

    /**
     * Returns all accepted MIME values assigned to an extension
     *
     * @return list<MimeType>
     */
    public function mimeTypesForExtension(string $extension): array
    {
        $definition = $this->definitionForExtension($extension);

        if ($definition === null) {
            return [];
        }

        return [
            $definition->canonicalMime(),
            ...$definition->aliases(),
        ];
    }

    /**
     * Returns all known extensions assigned to a detected MIME value
     *
     * @return list<string>
     */
    public function extensionsForMime(MimeType|string $mime): array
    {
        if ($mime instanceof MimeType) {
            $value = $mime->value();
        } else {
            $value = MimeType::fromString($mime)->value();
        }

        $definition = $this->byMime[$value] ?? null;

        if ($definition === null) {
            return [];
        }

        return $definition->extensions();
    }

    /**
     * Checks a concrete detected type against the extension's accepted current values
     */
    public function matches(string $extension, MimeType|string $mime): bool
    {
        if ($mime instanceof MimeType) {
            $value = $mime->value();
        } else {
            $value = MimeType::fromString($mime)->value();
        }

        foreach ($this->mimeTypesForExtension($extension) as $accepted) {
            if ($accepted->value() === $value) {
                return true;
            }
        }

        return false;
    }
}
