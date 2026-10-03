<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Localization;

use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Localization\Config\LocalizationConfig;
use Lemonade\Framework\Localization\Config\LocalizationUrlConfig;
use Lemonade\Framework\Localization\FileTranslationSourceCatalog;
use Lemonade\Framework\Localization\FileTranslator;
use Lemonade\Framework\Localization\TranslationOverrideProviderInterface;
use Lemonade\Framework\Localization\TranslationResourceRegistry;
use Lemonade\Framework\Localization\TranslationSourceEntry;
use PHPUnit\Framework\TestCase;

/**
 * Verifies source provenance and mutable runtime overlays without persistence dependencies
 */
final class TranslationRuntimeExtensionTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'lemonade-translation-runtime-' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        $this->deleteRecursive($this->root);
    }

    public function testSourceCatalogFlattensCoverageAndRetainsResourceProvenance(): void
    {
        $first = $this->root . '/first/Resources/lang';
        $second = $this->root . '/second/Resources/lang';
        $this->writeResource($first, 'cs', 'catalog', ['nested' => ['title' => 'First']]);
        $this->writeResource($first, 'en', 'catalog', ['nested' => ['title' => 'First EN']]);
        $this->writeResource($second, 'cs', 'catalog', ['nested' => ['title' => 'Second']]);

        $resources = new TranslationResourceRegistry();
        $resources->register($first, 'package.first');
        $resources->register($second, 'package.second');
        $catalog = new FileTranslationSourceCatalog($this->context(), $resources);

        self::assertContains('cs', $catalog->locales());
        self::assertContains('en', $catalog->locales());
        self::assertContains('catalog', $catalog->groups('cs'));

        $entry = $this->entry($catalog->entries('cs'), 'catalog', 'nested.title');
        self::assertSame('Second', $entry->value);
        self::assertCount(2, $entry->contributions);
        self::assertSame('package.first', $entry->contributions[0]->provenance->owner);
        self::assertSame('package.second', $entry->contributions[1]->provenance->owner);
    }

    public function testTargetOverrideIsUsedByGetGroupAndAllWithoutSourceCacheInvalidation(): void
    {
        $this->writeApplication('cs', 'messages', ['hello' => 'Ahoj']);
        $this->writeApplication('en', 'messages', ['hello' => 'Hello']);
        $overrides = new InMemoryTranslationOverrides();
        $translator = $this->translator($overrides);

        self::assertSame('Hello', $translator->get('messages.hello'));

        $overrides->put('en', 'messages', 'hello', 'Override');

        self::assertSame('Override', $translator->get('messages.hello'));
        self::assertSame('Override', $translator->group('messages')['hello']);
        self::assertSame('Override', $translator->all()['messages']['hello']);

        $overrides->remove('en', 'messages', 'hello');

        self::assertSame('Hello', $translator->get('messages.hello'));
        self::assertSame('Hello', $translator->group('messages')['hello']);
        self::assertSame('Hello', $translator->all()['messages']['hello']);
    }

    public function testFallbackOverrideUsesTheSameLocalePriorityAsFallbackSource(): void
    {
        $this->writeApplication('cs', 'messages', [
            'shared' => 'Czech source',
            'fallback_only' => 'Czech source',
        ]);
        $this->writeApplication('en', 'messages', ['shared' => 'English source']);
        $overrides = new InMemoryTranslationOverrides();
        $overrides->put('cs', 'messages', 'shared', 'Czech override');
        $overrides->put('cs', 'messages', 'fallback_only', 'Fallback override');
        $translator = $this->translator($overrides);

        self::assertSame('English source', $translator->get('messages.shared'));
        self::assertSame('Fallback override', $translator->get('messages.fallback_only'));
        self::assertSame(
            ['shared' => 'English source', 'fallback_only' => 'Fallback override'],
            $translator->group('messages'),
        );
        self::assertSame(
            ['shared' => 'English source', 'fallback_only' => 'Fallback override'],
            $translator->all()['messages'],
        );
    }

    /**
     * @param list<TranslationSourceEntry> $entries
     */
    private function entry(array $entries, string $group, string $key): TranslationSourceEntry
    {
        foreach ($entries as $entry) {
            if ($entry->group === $group && $entry->key === $key) {
                return $entry;
            }
        }

        self::fail(sprintf('Translation source entry "%s.%s" was not found.', $group, $key));
    }

    private function translator(InMemoryTranslationOverrides $overrides): FileTranslator
    {
        return new FileTranslator(
            context: $this->context(),
            config: new LocalizationConfig(
                defaultLocale: 'en',
                fallbackLocale: 'cs',
                supportedLocales: ['cs', 'en'],
                url: new LocalizationUrlConfig(false, 'localized.', '/{locale}', 'locale', false),
            ),
            overrides: $overrides,
        );
    }

    private function context(): ApplicationContext
    {
        return new ApplicationContext(Environment::Testing, new Path($this->root), DebugMode::disabled());
    }

    /**
     * @param array<string, string|array<string, mixed>> $lines
     */
    private function writeResource(string $resource, string $locale, string $group, array $lines): void
    {
        $this->writeFile($resource . '/' . $locale . '/' . $group . '.php', $lines);
    }

    /**
     * @param array<string, string|array<string, mixed>> $lines
     */
    private function writeApplication(string $locale, string $group, array $lines): void
    {
        $this->writeFile($this->root . '/src/Language/' . $locale . '/' . $group . '.php', $lines);
    }

    /**
     * @param array<string, string|array<string, mixed>> $lines
     */
    private function writeFile(string $path, array $lines): void
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($path, "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($lines, true) . ";\n");
    }

    private function deleteRecursive(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        if (is_file($path) || is_link($path)) {
            unlink($path);

            return;
        }

        $items = scandir($path);
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item !== '.' && $item !== '..') {
                $this->deleteRecursive($path . DIRECTORY_SEPARATOR . $item);
            }
        }

        rmdir($path);
    }
}

/**
 * Supplies mutable test values through the same read-only runtime boundary as a future repository
 */
final class InMemoryTranslationOverrides implements TranslationOverrideProviderInterface
{
    /**
     * @var array<string, array<string, array<string, string>>>
     */
    private array $overrides = [];

    public function put(string $locale, string $group, string $key, string $value): void
    {
        $this->overrides[$locale][$group][$key] = $value;
    }

    public function remove(string $locale, string $group, string $key): void
    {
        unset($this->overrides[$locale][$group][$key]);
    }

    public function groups(string $locale): array
    {
        return array_keys(array_filter(
            $this->overrides[$locale] ?? [],
            static fn(array $values): bool => $values !== [],
        ));
    }

    public function group(string $locale, string $group): array
    {
        return $this->overrides[$locale][$group] ?? [];
    }
}
