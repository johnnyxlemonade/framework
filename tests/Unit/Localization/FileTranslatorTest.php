<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Localization;

use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Localization\FileTranslator;
use PHPUnit\Framework\TestCase;

final class FileTranslatorTest extends TestCase
{
    private string $root;

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
        return new FileTranslator(
            new ApplicationContext(
                Environment::Testing,
                new Path($this->root),
                DebugMode::disabled(),
            ),
            new Config($config),
        );
    }

    /**
     * @param array<string, string> $lines
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
     * @param array<mixed> $lines
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
