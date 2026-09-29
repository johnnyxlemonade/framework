<?php

declare(strict_types=1);

namespace Lemonade\Framework\Mime;

use InvalidArgumentException;

/**
 * Defines one format family with canonical detection, safe suffixes, and risk metadata.
 */
final readonly class MimeTypeDefinition
{
    /**
     * Creates a normalized format family whose aliases are current accepted detection results.
     *
     * @param list<string> $extensions
     * @param list<MimeType|string> $aliases
     */
    public function __construct(
        private MimeType $canonicalMime,
        array $extensions,
        array $aliases = [],
        private MimeRisk $risk = MimeRisk::Passive,
    ) {
        $normalizedExtensions = [];

        foreach ($extensions as $extension) {
            $normalized = MimeTypeCatalog::normalizeExtension($extension);

            if (isset($normalizedExtensions[$normalized])) {
                throw new InvalidArgumentException('MIME definition extensions must be unique.');
            }

            $normalizedExtensions[$normalized] = $normalized;
        }

        if (count($normalizedExtensions) === 0) {
            throw new InvalidArgumentException('MIME definition requires an extension.');
        }

        $normalizedAliases = [];

        foreach ($aliases as $alias) {
            if ($alias instanceof MimeType) {
                $mime = $alias;
            } else {
                $mime = MimeType::fromString($alias);
            }

            if ($mime->value() === $this->canonicalMime->value() || isset($normalizedAliases[$mime->value()])) {
                throw new InvalidArgumentException('MIME definition aliases must be unique and non-canonical.');
            }

            $normalizedAliases[$mime->value()] = $mime;
        }

        $this->extensions = array_values($normalizedExtensions);
        $this->aliases = array_values($normalizedAliases);
    }

    /**
     * @var non-empty-list<string>
     */
    private array $extensions;

    /**
     * @var list<MimeType>
     */
    private array $aliases;

    /**
     * Returns the preferred media type for the format family
     */
    public function canonicalMime(): MimeType
    {
        return $this->canonicalMime;
    }

    /**
     * Returns every normalized suffix belonging to the format family
     *
     * @return non-empty-list<string>
     */
    public function extensions(): array
    {
        return $this->extensions;
    }

    /**
     * Returns additional server-detected MIME values accepted for the format family
     *
     * @return list<MimeType>
     */
    public function aliases(): array
    {
        return $this->aliases;
    }

    /**
     * Returns descriptive handling risk without authorizing an upload
     */
    public function risk(): MimeRisk
    {
        return $this->risk;
    }
}
