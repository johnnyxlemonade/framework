<?php

declare(strict_types=1);

namespace Lemonade\Framework\Localization;

use Lemonade\Framework\Core\Context\ApplicationContext;

/**
 * Reads and combines physical translation files while retaining their provenance
 */
final class FileTranslationSourceCatalog implements TranslationSourceCatalogInterface
{
    /**
     * @var array<string, list<TranslationSourceEntry>>
     */
    private array $entriesByLocale = [];

    /**
     * Configures application paths and the registry of provider resource contributions
     */
    public function __construct(
        private readonly ApplicationContext $context,
        private readonly ?TranslationResourceRegistry $resources = null,
    ) {
    }

    /**
     * Returns locales with at least one physical source file
     *
     * @return list<string>
     */
    public function locales(): array
    {
        $this->freezeResources();
        $locales = [];

        foreach ($this->sourceDirectories() as $source) {
            if (!is_dir($source['directory'])) {
                continue;
            }

            $directories = glob($source['directory'] . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
            if ($directories === false) {
                continue;
            }

            foreach ($directories as $directory) {
                $locale = basename($directory);
                if ($locale !== '') {
                    $locales[$locale] = true;
                }
            }
        }

        $result = array_keys($locales);
        sort($result, SORT_STRING);

        return $result;
    }

    /**
     * Returns groups physically covered by the selected locale
     *
     * @return list<string>
     */
    public function groups(string $locale): array
    {
        $groups = [];
        foreach ($this->entries($locale) as $entry) {
            $groups[$entry->group] = true;
        }

        $result = array_keys($groups);
        sort($result, SORT_STRING);

        return $result;
    }

    /**
     * Returns effective source values and all contributions without locale fallback
     *
     * @return list<TranslationSourceEntry>
     */
    public function entries(?string $locale = null): array
    {
        if ($locale !== null) {
            return $this->entriesForLocale($locale);
        }

        $entries = [];
        foreach ($this->locales() as $availableLocale) {
            array_push($entries, ...$this->entriesForLocale($availableLocale));
        }

        return $entries;
    }

    /**
     * Returns effective source values for one group and exact locale
     *
     * @return array<string, string>
     */
    public function lines(string $locale, string $group): array
    {
        $lines = [];
        foreach ($this->entriesForLocale($locale) as $entry) {
            if ($entry->group === $group) {
                $lines[$entry->key] = $entry->value;
            }
        }

        return $lines;
    }

    /**
     * @return list<TranslationSourceEntry>
     */
    private function entriesForLocale(string $locale): array
    {
        $this->freezeResources();
        if (isset($this->entriesByLocale[$locale])) {
            return $this->entriesByLocale[$locale];
        }

        /** @var array<string, array{group:string,key:string,contributions:list<TranslationSourceContribution>}> $collected */
        $collected = [];
        foreach ($this->sourceDirectories() as $source) {
            $directory = $source['directory'] . DIRECTORY_SEPARATOR . $locale;
            if (!is_dir($directory)) {
                continue;
            }

            $files = glob($directory . DIRECTORY_SEPARATOR . '*.php');
            if ($files === false) {
                continue;
            }
            sort($files, SORT_STRING);

            foreach ($files as $file) {
                $group = pathinfo($file, PATHINFO_FILENAME);
                if ($group === '') {
                    continue;
                }

                foreach ($this->loadFile($file) as $key => $value) {
                    $identity = $group . "\0" . $key;
                    $collected[$identity] ??= [
                        'group' => $group,
                        'key' => $key,
                        'contributions' => [],
                    ];
                    $collected[$identity]['contributions'][] = new TranslationSourceContribution(
                        value: $value,
                        provenance: new TranslationSourceProvenance(
                            kind: $source['kind'],
                            directory: $source['directory'],
                            owner: $source['owner'],
                        ),
                    );
                }
            }
        }

        $entries = [];
        foreach ($collected as $entry) {
            $contributions = $entry['contributions'];
            if ($contributions === []) {
                continue;
            }
            $effective = $contributions[count($contributions) - 1];
            $entries[] = new TranslationSourceEntry(
                locale: $locale,
                group: $entry['group'],
                key: $entry['key'],
                value: $effective->value,
                contributions: $contributions,
            );
        }

        return $this->entriesByLocale[$locale] = $entries;
    }

    /**
     * @return array<string, string>
     */
    private function loadFile(string $path): array
    {
        $loaded = require $path;

        return is_array($loaded) ? $this->flattenLines($loaded) : [];
    }

    /**
     * @param array<mixed> $lines
     * @return array<string, string>
     */
    private function flattenLines(array $lines, string $prefix = ''): array
    {
        $result = [];

        foreach ($lines as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            $fullKey = $prefix !== '' ? $prefix . '.' . $key : $key;
            if (is_string($value)) {
                $result[$fullKey] = $value;

                continue;
            }
            if (is_array($value)) {
                $result = array_merge($result, $this->flattenLines($value, $fullKey));
            }
        }

        return $result;
    }

    /**
     * @return list<array{kind:string,directory:string,owner:string|null}>
     */
    private function sourceDirectories(): array
    {
        $directories = [
            [
                'kind' => 'framework',
                'directory' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Language',
                'owner' => null,
            ],
            [
                'kind' => 'application',
                'directory' => $this->context->path('src/Language'),
                'owner' => null,
            ],
        ];

        foreach ($this->resources?->resources() ?? [] as $resource) {
            $directories[] = [
                'kind' => 'resource',
                'directory' => $resource->directory,
                'owner' => $resource->owner,
            ];
        }

        $directories[] = [
            'kind' => 'application_override',
            'directory' => $this->context->appPath('Language'),
            'owner' => null,
        ];

        return $directories;
    }

    /**
     * Freezes resource registration before the source catalog is first read
     */
    private function freezeResources(): void
    {
        $this->resources?->freeze();
    }
}
