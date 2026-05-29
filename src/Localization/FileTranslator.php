<?php

declare(strict_types=1);

namespace Lemonade\Framework\Localization;

use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Core\Context\ApplicationContext;

final class FileTranslator implements TranslatorInterface
{
    /**
     * @var array<string, array<string, string>>
     */
    private array $cache = [];
    private ?string $localeOverride = null;

    public function __construct(
        private readonly ApplicationContext $context,
        private readonly Config $config,
    ) {}

    public function setLocale(?string $locale): self
    {
        $value = $locale !== null ? trim($locale) : '';
        $this->localeOverride = $value !== '' ? $value : null;

        return $this;
    }

    public function locale(): ?string
    {
        return $this->localeOverride;
    }

    public function get(string $key, array $replacements = [], ?string $locale = null): string
    {
        [$group, $item] = $this->splitKey($key);
        $locale = $this->resolveLocale($locale);
        $fallbackLocale = $this->fallbackLocale();

        $line = $this->lines($group, $locale)[$item]
            ?? $this->lines($group, $fallbackLocale)[$item]
            ?? $key;

        if ($replacements === []) {
            return $line;
        }

        $search = [];
        $replace = [];
        foreach ($replacements as $name => $value) {
            $search[] = '{' . $name . '}';
            $replace[] = (string) $value;
        }

        return str_replace($search, $replace, $line);
    }

    public function group(string $group, ?string $locale = null): array
    {
        $resolvedLocale = $this->resolveLocale($locale);
        $primary = $this->lines($group, $resolvedLocale);
        $fallback = $this->lines($group, $this->fallbackLocale());

        return array_replace($fallback, $primary);
    }

    public function all(?string $locale = null): array
    {
        $resolvedLocale = $this->resolveLocale($locale);
        $groups = $this->groupNames($resolvedLocale);
        $output = [];

        foreach ($groups as $group) {
            $output[$group] = $this->group($group, $resolvedLocale);
        }

        return $output;
    }

    /**
     * @return array{0:string,1:string}
     */
    private function splitKey(string $key): array
    {
        $parts = explode('.', $key, 2);
        if (count($parts) !== 2) {
            return ['messages', $key];
        }

        return [$parts[0], $parts[1]];
    }

    private function resolveLocale(?string $locale): string
    {
        $resolved = $locale ?? $this->localeOverride ?? $this->defaultLocale();
        $resolved = trim($resolved);

        return $resolved !== '' ? $resolved : $this->fallbackLocale();
    }

    /**
     * @return array<string, string>
     */
    private function lines(string $group, string $locale): array
    {
        $cacheKey = $locale . ':' . $group;
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $frameworkFile = $this->context->path('src/Language/' . $locale . '/' . $group . '.php');
        $packageFrameworkFile = $this->frameworkLanguagePath($locale, $group);
        $appFile = $this->context->appPath('Language/' . $locale . '/' . $group . '.php');

        $frameworkLines = array_replace(
            $this->loadFile($packageFrameworkFile),
            $this->loadFile($frameworkFile),
        );
        $appLines = $this->loadFile($appFile);

        return $this->cache[$cacheKey] = array_replace($frameworkLines, $appLines);
    }

    /**
     * @return array<string, string>
     */
    private function loadFile(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $loaded = require $path;

        if (!is_array($loaded)) {
            return [];
        }

        return $this->flattenLines($loaded);
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
                $result = array_merge(
                    $result,
                    $this->flattenLines($value, $fullKey),
                );
            }
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    private function groupNames(string $locale): array
    {
        $fallbackLocale = $this->fallbackLocale();
        $frameworkDir = $this->context->path('src/Language/' . $locale);
        $packageFrameworkDir = $this->frameworkLanguageDirectory($locale);
        $appDir = $this->context->appPath('Language/' . $locale);
        $fallbackFrameworkDir = $this->context->path('src/Language/' . $fallbackLocale);
        $fallbackPackageFrameworkDir = $this->frameworkLanguageDirectory($fallbackLocale);
        $fallbackAppDir = $this->context->appPath('Language/' . $fallbackLocale);

        $names = array_merge(
            $this->collectGroupNamesFromDirectory($frameworkDir),
            $this->collectGroupNamesFromDirectory($packageFrameworkDir),
            $this->collectGroupNamesFromDirectory($appDir),
            $this->collectGroupNamesFromDirectory($fallbackFrameworkDir),
            $this->collectGroupNamesFromDirectory($fallbackPackageFrameworkDir),
            $this->collectGroupNamesFromDirectory($fallbackAppDir),
        );

        $names = array_values(array_unique($names));
        sort($names);

        return $names;
    }

    /**
     * @return list<string>
     */
    private function collectGroupNamesFromDirectory(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $files = glob(rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . '*.php');
        if (!is_array($files)) {
            return [];
        }

        $names = [];
        foreach ($files as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    private function defaultLocale(): string
    {
        $localeConfig = $this->config->get('localization.default_locale', 'cs');
        $locale = is_scalar($localeConfig) ? (string) $localeConfig : 'cs';
        $locale = trim($locale);

        return $locale !== '' ? $locale : 'cs';
    }

    private function fallbackLocale(): string
    {
        $localeConfig = $this->config->get('localization.fallback_locale', 'cs');
        $locale = is_scalar($localeConfig) ? (string) $localeConfig : 'cs';
        $locale = trim($locale);

        return $locale !== '' ? $locale : 'cs';
    }

    private function frameworkLanguageDirectory(string $locale): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Language' . DIRECTORY_SEPARATOR . $locale;
    }

    private function frameworkLanguagePath(string $locale, string $group): string
    {
        return $this->frameworkLanguageDirectory($locale) . DIRECTORY_SEPARATOR . $group . '.php';
    }
}
