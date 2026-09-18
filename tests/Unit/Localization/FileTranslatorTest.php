<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Localization;

use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Localization\Config\LocalizationConfig;
use Lemonade\Framework\Localization\Config\LocalizationUrlConfig;
use Lemonade\Framework\Localization\FileTranslator;
use Lemonade\Framework\Localization\TranslationResourceRegistry;
use PHPUnit\Framework\TestCase;

/**
 * PHPStan type aliases cannot be recursive. The explicit mixed value at deeper
 * levels preserves arbitrary nested PHP translation catalogs at this test-file
 * serialization boundary.
 *
 * @phpstan-type TranslationCatalog array<string, string|array<string, mixed>>
 */
final class FileTranslatorTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'lemonade-translator-' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        $this->deleteRecursive($this->root);
    }

    public function testGetReturnsValueFromLanguageFile(): void
    {
        $this->writeLang('src', 'cs', 'messages', ['hello' => 'Ahoj']);
        $translator = $this->translator();

        self::assertSame('Ahoj', $translator->get('messages.hello'));
    }

    public function testGetWithoutGroupUsesImplicitMessagesGroup(): void
    {
        $this->writeLang('src', 'cs', 'messages', ['hello' => 'Ahoj']);
        $translator = $this->translator();

        self::assertSame('Ahoj', $translator->get('hello'));
    }

    public function testMissingTranslationReturnsOriginalKey(): void
    {
        $translator = $this->translator();

        self::assertSame('messages.missing', $translator->get('messages.missing'));
    }

    public function testReplacementsReplacePlaceholdersAndCastScalarsToString(): void
    {
        $this->writeLang('src', 'cs', 'messages', [
            'welcome' => 'Hello {name}, you have {count} messages.',
        ]);
        $translator = $this->translator();

        self::assertSame(
            'Hello John, you have 5 messages.',
            $translator->get('messages.welcome', ['name' => 'John', 'count' => 5]),
        );
    }

    public function testExplicitLocaleParameterIsUsed(): void
    {
        $this->writeLang('src', 'cs', 'messages', ['hello' => 'Ahoj']);
        $this->writeLang('src', 'en', 'messages', ['hello' => 'Hello']);
        $translator = $this->translator(['localization' => ['default_locale' => 'cs']]);

        self::assertSame('Hello', $translator->get('messages.hello', [], 'en'));
    }

    public function testSetLocaleOverrideAndReset(): void
    {
        $this->writeLang('src', 'cs', 'messages', ['hello' => 'Ahoj']);
        $this->writeLang('src', 'en', 'messages', ['hello' => 'Hello']);
        $translator = $this->translator(['localization' => ['default_locale' => 'cs']]);

        $translator->setLocale('en');
        self::assertSame('Hello', $translator->get('messages.hello'));

        $translator->setLocale('');
        self::assertSame('Ahoj', $translator->get('messages.hello'));

        $translator->setLocale(null);
        self::assertSame('Ahoj', $translator->get('messages.hello'));
    }

    public function testInvalidDefaultLocaleFallsBackToCs(): void
    {
        $this->writeLang('src', 'cs', 'messages', ['hello' => 'Ahoj']);

        $translatorInvalidScalar = $this->translator(['localization' => ['default_locale' => ['invalid']]]);
        self::assertSame('Ahoj', $translatorInvalidScalar->get('messages.hello'));

        $translatorEmptyString = $this->translator([
            'localization' => [
                'default_locale' => '',
                'fallback_locale' => 'cs',
            ],
        ]);
        self::assertSame('Ahoj', $translatorEmptyString->get('messages.hello'));
    }

    public function testPrimaryMissingFallsBackToFallbackLocale(): void
    {
        $this->writeLang('src', 'cs', 'messages', ['hello' => 'Ahoj']);
        $translator = $this->translator([
            'localization' => [
                'default_locale' => 'en',
                'fallback_locale' => 'cs',
            ],
        ]);

        self::assertSame('Ahoj', $translator->get('messages.hello'));
    }

    public function testMissingInPrimaryAndFallbackReturnsOriginalKey(): void
    {
        $translator = $this->translator([
            'localization' => [
                'default_locale' => 'en',
                'fallback_locale' => 'cs',
            ],
        ]);

        self::assertSame('messages.hello', $translator->get('messages.hello'));
    }

    public function testAppLanguageOverridesFrameworkAndFrameworkCompletesMissingKeys(): void
    {
        $this->writeLang('src', 'cs', 'messages', ['hello' => 'Framework', 'only_fw' => 'FW']);
        $this->writeLang('app', 'cs', 'messages', ['hello' => 'App', 'only_app' => 'APP']);
        $translator = $this->translator();

        self::assertSame('App', $translator->get('messages.hello'));
        self::assertSame('FW', $translator->get('messages.only_fw'));
        self::assertSame('APP', $translator->get('messages.only_app'));
    }

    public function testRegisteredTranslationResourceIsDiscoveredAndAppCanOverrideIt(): void
    {
        $resource = $this->root . DIRECTORY_SEPARATOR . 'module' . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'lang';
        $this->writeRaw(
            $resource . DIRECTORY_SEPARATOR . 'cs' . DIRECTORY_SEPARATOR . 'auth.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn ['login' => ['title' => 'Module login']];\n",
        );
        $this->writeLang('app', 'cs', 'auth', ['login' => ['title' => 'Application login']]);

        $resources = new TranslationResourceRegistry();
        $resources->register($resource);
        $translator = new FileTranslator(
            new ApplicationContext(Environment::Testing, new Path($this->root), DebugMode::disabled()),
            new LocalizationConfig(
                defaultLocale: 'cs',
                fallbackLocale: 'en',
                supportedLocales: ['cs', 'en'],
                url: new LocalizationUrlConfig(false, 'localized.', '/{locale}', 'locale', false),
            ),
            $resources,
        );

        self::assertSame('Application login', $translator->get('auth.login.title'));
        self::assertArrayHasKey('auth', $translator->all('cs'));
    }

    public function testProviderResourcesMergeInRegistrationOrderAndApplicationHasFinalOverride(): void
    {
        $locale = 'fr-CA';
        $group = 'catalog';
        $providerA = $this->root . DIRECTORY_SEPARATOR . 'provider-a' . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'lang';
        $providerB = $this->root . DIRECTORY_SEPARATOR . 'provider-b' . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'lang';

        $this->writeLang('src', $locale, $group, [
            'chain' => 'framework',
            'framework_only' => 'framework only',
            'nested' => ['title' => 'framework title', 'description' => 'framework description'],
        ]);
        $this->writeResourceLang($providerA, $locale, $group, [
            'chain' => 'provider A',
            'provider_a_only' => 'provider A only',
            'nested' => ['title' => 'provider A title', 'description' => 'provider A description'],
        ]);
        $this->writeResourceLang($providerB, $locale, $group, [
            'chain' => 'provider B',
            'provider_b_only' => 'provider B only',
            'nested' => ['title' => 'provider B title'],
        ]);
        $this->writeLang('app', $locale, $group, [
            'chain' => 'application',
            'application_only' => 'application only',
        ]);

        $resources = new TranslationResourceRegistry();
        $resources->register($providerA);
        $resources->register($providerB);
        $translator = $this->translatorWithResources($resources, $locale);

        self::assertSame('application', $translator->get('catalog.chain'));
        self::assertSame('framework only', $translator->get('catalog.framework_only'));
        self::assertSame('provider A only', $translator->get('catalog.provider_a_only'));
        self::assertSame('provider B only', $translator->get('catalog.provider_b_only'));
        self::assertSame('application only', $translator->get('catalog.application_only'));
        self::assertSame('provider B title', $translator->get('catalog.nested.title'));
        self::assertSame('provider A description', $translator->get('catalog.nested.description'));
    }

    public function testTranslationResourceRegistryDeduplicatesCanonicalPaths(): void
    {
        $resource = $this->root . DIRECTORY_SEPARATOR . 'module' . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'lang';
        $this->writeResourceLang($resource, 'fr-CA', 'catalog', ['title' => 'Example']);

        $resources = new TranslationResourceRegistry();
        $resources->register($resource);
        $resources->register($resource . DIRECTORY_SEPARATOR);
        $resources->register($this->root . DIRECTORY_SEPARATOR . 'module' . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'lang');

        $link = $this->root . DIRECTORY_SEPARATOR . 'resource-link';
        if (DIRECTORY_SEPARATOR !== '\\' && function_exists('symlink') && @symlink($resource, $link)) {
            $resources->register($link);
        }

        self::assertSame([realpath($resource)], $resources->directories());
    }

    public function testTranslationResourceRegistryRejectsMissingResourceRoot(): void
    {
        $resources = new TranslationResourceRegistry();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must exist and be a directory');

        $resources->register($this->root . DIRECTORY_SEPARATOR . 'missing');
    }

    public function testTranslationResourceRegistryRejectsFileAsResourceRoot(): void
    {
        $file = $this->root . DIRECTORY_SEPARATOR . 'not-a-directory.php';
        $this->writeRaw($file, "<?php\n");
        $resources = new TranslationResourceRegistry();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must exist and be a directory');

        $resources->register($file);
    }

    public function testTranslationResourceRegistrationIsRejectedAfterTranslatorFirstUse(): void
    {
        $locale = 'fr-CA';
        $this->writeLang('src', $locale, 'catalog', ['title' => 'Framework catalog']);
        $resource = $this->root . DIRECTORY_SEPARATOR . 'module' . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'lang';
        $this->writeResourceLang($resource, $locale, 'catalog', ['title' => 'Provider catalog']);

        $resources = new TranslationResourceRegistry();
        $translator = $this->translatorWithResources($resources, $locale);
        self::assertSame('Framework catalog', $translator->get('catalog.title'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must be registered during provider registration');

        $resources->register($resource);
    }

    public function testGroupReturnsMergedFallbackAndPrimaryWithPrimaryPriority(): void
    {
        $this->writeLang('src', 'cs', 'messages', ['hello' => 'Ahoj', 'from_fallback' => 'cs-only']);
        $this->writeLang('src', 'en', 'messages', ['hello' => 'Hello', 'from_primary' => 'en-only']);
        $translator = $this->translator([
            'localization' => [
                'default_locale' => 'en',
                'fallback_locale' => 'cs',
            ],
        ]);

        self::assertSame([
            'hello' => 'Hello',
            'from_fallback' => 'cs-only',
            'from_primary' => 'en-only',
        ], $translator->group('messages', 'en'));
    }

    public function testAllReturnsGroupsFromResolvedAndFallbackLocales(): void
    {
        $this->writeLang('src', 'cs', 'messages', ['hello' => 'Ahoj']);
        $this->writeLang('src', 'cs', 'validation', ['required' => 'Povinné']);
        $this->writeLang('src', 'en', 'messages', ['hello' => 'Hello']);
        $translator = $this->translator([
            'localization' => [
                'default_locale' => 'en',
                'fallback_locale' => 'cs',
            ],
        ]);

        $all = $translator->all('en');

        self::assertArrayHasKey('messages', $all);
        self::assertArrayHasKey('validation', $all);
        self::assertSame('Hello', $all['messages']['hello']);
        self::assertSame('{field} is required.', $all['validation']['required']);
    }

    public function testAllReturnsEmptyArrayForMissingLanguageDirectories(): void
    {
        $translator = $this->translator();

        $all = $translator->all('en');

        self::assertArrayHasKey('validation', $all);
        self::assertSame('{field} is required.', $all['validation']['required']);
    }

    public function testInvalidLanguageFilesAndEntriesAreIgnored(): void
    {
        $this->writeRaw($this->langPath('src', 'cs', 'messages'), '<?php return "invalid";');
        $this->writeLangMixed($this->langPath('src', 'cs', 'validation'), [
            'ok' => 'valid',
            1 => 'nope',
            'arr' => ['x'],
        ]);

        $translator = $this->translator();

        self::assertSame('messages.hello', $translator->get('messages.hello'));
        self::assertSame('valid', $translator->get('validation.ok'));
        self::assertSame('validation.arr', $translator->get('validation.arr'));
    }

    public function testNestedKeysAreAvailableViaDotNotation(): void
    {
        $this->writeLangNested($this->langPath('src', 'en', 'documentation'), [
            'modules' => [
                'core' => [
                    'title' => 'Application Core',
                ],
            ],
        ]);

        $translator = $this->translator([
            'localization' => [
                'default_locale' => 'en',
                'fallback_locale' => 'en',
            ],
        ]);

        self::assertSame(
            'Application Core',
            $translator->get('documentation.modules.core.title'),
        );
    }

    public function testNestedKeyFallsBackToFallbackLocale(): void
    {
        $this->writeLangNested($this->langPath('src', 'cs', 'documentation'), [
            'modules' => [
                'core' => [
                    'title' => 'Jádro aplikace',
                ],
            ],
        ]);

        $translator = $this->translator([
            'localization' => [
                'default_locale' => 'en',
                'fallback_locale' => 'cs',
            ],
        ]);

        self::assertSame(
            'Jádro aplikace',
            $translator->get('documentation.modules.core.title'),
        );
    }

    public function testAppOverridesFrameworkForNestedKeys(): void
    {
        $this->writeLangNested($this->langPath('src', 'en', 'documentation'), [
            'modules' => [
                'core' => [
                    'title' => 'Framework Core',
                ],
            ],
        ]);
        $this->writeLangNested($this->langPath('app', 'en', 'documentation'), [
            'modules' => [
                'core' => [
                    'title' => 'Application Core',
                ],
            ],
        ]);

        $translator = $this->translator([
            'localization' => [
                'default_locale' => 'en',
                'fallback_locale' => 'en',
            ],
        ]);

        self::assertSame(
            'Application Core',
            $translator->get('documentation.modules.core.title'),
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private function translator(array $config = []): FileTranslator
    {
        $localization = is_array($config['localization'] ?? null) ? $config['localization'] : [];
        $url = is_array($localization['url'] ?? null) ? $localization['url'] : [];

        return new FileTranslator(
            new ApplicationContext(
                Environment::Testing,
                new Path($this->root),
                DebugMode::disabled(),
            ),
            new LocalizationConfig(
                defaultLocale: $this->stringOr($localization['default_locale'] ?? null, 'cs'),
                fallbackLocale: $this->stringOr($localization['fallback_locale'] ?? null, 'cs'),
                supportedLocales: ['cs', 'en'],
                url: new LocalizationUrlConfig(
                    enabled: $this->boolOr($url['enabled'] ?? null, false),
                    localizedRouteNamePrefix: $this->stringOr($url['localized_route_name_prefix'] ?? null, 'localized.'),
                    routePrefix: $this->stringOr($url['route_prefix'] ?? null, '/{locale}'),
                    localeParameter: $this->stringOr($url['locale_parameter'] ?? null, 'locale'),
                    includeDefaultLocale: $this->boolOr($url['include_default_locale'] ?? null, false),
                ),
            ),
        );
    }

    private function translatorWithResources(TranslationResourceRegistry $resources, string $locale): FileTranslator
    {
        return new FileTranslator(
            new ApplicationContext(
                Environment::Testing,
                new Path($this->root),
                DebugMode::disabled(),
            ),
            new LocalizationConfig(
                defaultLocale: $locale,
                fallbackLocale: $locale,
                supportedLocales: [$locale],
                url: new LocalizationUrlConfig(false, 'localized.', '/{locale}', 'locale', false),
            ),
            $resources,
        );
    }

    private function stringOr(mixed $value, string $default): string
    {
        if (!is_scalar($value)) {
            return $default;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? $default : $normalized;
    }

    private function boolOr(mixed $value, bool $default): bool
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
     * @param TranslationCatalog $lines
     */
    private function writeLang(string $scope, string $locale, string $group, array $lines): void
    {
        $path = $this->langPath($scope, $locale, $group);
        $code = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($lines, true) . ";\n";
        $this->writeRaw($path, $code);
    }

    /**
     * @param array<mixed> $lines
     */
    private function writeLangMixed(string $path, array $lines): void
    {
        $code = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($lines, true) . ";\n";
        $this->writeRaw($path, $code);
    }

    /**
     * @param TranslationCatalog $lines
     */
    private function writeResourceLang(string $resource, string $locale, string $group, array $lines): void
    {
        $code = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($lines, true) . ";\n";
        $this->writeRaw(
            $resource . DIRECTORY_SEPARATOR . $locale . DIRECTORY_SEPARATOR . $group . '.php',
            $code,
        );
    }

    /**
     * @param TranslationCatalog $lines
     */
    private function writeLangNested(string $path, array $lines): void
    {
        $code = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($lines, true) . ";\n";
        $this->writeRaw($path, $code);
    }

    private function langPath(string $scope, string $locale, string $group): string
    {
        $base = $scope === 'src' ? 'src' : 'app';

        return $this->root
            . DIRECTORY_SEPARATOR . $base
            . DIRECTORY_SEPARATOR . 'Language'
            . DIRECTORY_SEPARATOR . $locale
            . DIRECTORY_SEPARATOR . $group . '.php';
    }

    private function writeRaw(string $path, string $content): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents($path, $content);
    }

    private function deleteRecursive(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        if (is_file($path) || is_link($path)) {
            @unlink($path);
            return;
        }

        $items = scandir($path);
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $this->deleteRecursive($path . DIRECTORY_SEPARATOR . $item);
        }

        @rmdir($path);
    }
}
