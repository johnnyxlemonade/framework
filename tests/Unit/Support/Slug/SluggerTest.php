<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Support\Slug;

use Lemonade\Framework\Support\Slug\Slugger;
use PHPUnit\Framework\TestCase;

final class SluggerTest extends TestCase
{
    /**
     * @dataProvider slugExamples
     */
    public function testSlugNormalizesText(string $input, string $expected): void
    {
        self::assertSame($expected, $this->slugger()->slug($input));
    }

    /**
     * @dataProvider localeSlugExamples
     */
    public function testSlugTransliteratesSupportedLocales(string $input, string $locale, string $expected): void
    {
        self::assertSame($expected, $this->slugger()->slug($input, locale: $locale));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function slugExamples(): iterable
    {
        yield 'Czech transliteration' => ['Žluťoučký kůň', 'zlutoucky-kun'];
        yield 'Czech title' => ['Jak navrhnout přehlednou administraci', 'jak-navrhnout-prehlednou-administraci'];
        yield 'whitespace' => ['  Ahoj   světe  ', 'ahoj-svete'];
        yield 'repeated hyphens' => ['Ahoj---světe', 'ahoj-svete'];
        yield 'edge hyphens' => ['---Ahoj---světe---', 'ahoj-svete'];
        yield 'special characters' => ['Ahoj!!! světe???', 'ahoj-svete'];
        yield 'empty value' => ['', ''];
        yield 'underscores are word separators' => ['___Ahoj_světe___', 'ahoj-svete'];
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function localeSlugExamples(): iterable
    {
        yield 'German' => ['Straße', 'de', 'strasse'];
        yield 'Polish' => ['Zażółć gęślą jaźń', 'pl', 'zazolc-gesla-jazn'];
        yield 'Russian' => ['Привет мир', 'ru', 'privet-mir'];
        yield 'Ukrainian' => ['Привіт світ', 'uk', 'privit-svit'];
        yield 'Turkish' => ['İstanbul çığ', 'tr', 'istanbul-cig'];
        yield 'Vietnamese' => ['Đặng', 'vi', 'dang'];
        yield 'Arabic' => ['سلام', 'ar', 'slam'];
        yield 'Persian' => ['گپ', 'fa', 'gp'];
    }

    public function testSlugTrimsTrailingHyphenAfterLengthLimit(): void
    {
        self::assertSame('ahoj', $this->slugger()->slug('Ahoj světe', maxLength: 5));
    }

    public function testSlugTrimsEdgeHyphensBeforeApplyingLengthLimit(): void
    {
        self::assertSame('aho', $this->slugger()->slug('---Ahoj', maxLength: 3));
    }

    public function testSlugUsesDefaultMaximumLengthOf120Characters(): void
    {
        self::assertSame(str_repeat('a', 120), $this->slugger()->slug(str_repeat('a', 121)));
    }

    public function testSlugSupportsPositionalAndNamedArguments(): void
    {
        $slugger = $this->slugger();
        $text = str_repeat('a', 81);

        self::assertSame('zlutoucky-kun', $slugger->slug('Žluťoučký kůň'));
        self::assertSame(str_repeat('a', 80), $slugger->slug($text, 80));
        self::assertSame('zlutoucky-kun', $slugger->slug('Žluťoučký kůň', 120, 'cs'));
        self::assertSame(str_repeat('a', 80), $slugger->slug($text, locale: 'cs', maxLength: 80));
    }

    public function testSlugWithNonPositiveLengthIsEmpty(): void
    {
        self::assertSame('', $this->slugger()->slug('Ahoj světe', maxLength: 0));
    }

    public function testSlugIsIdempotent(): void
    {
        $slugger = $this->slugger();
        $slug = $slugger->slug('Žluťoučký --- kůň!');

        self::assertSame($slug, $slugger->slug($slug));
    }

    public function testSlugNormalizesLocaleBeforeSelectingItsMap(): void
    {
        self::assertSame('caj', $this->slugger()->slug('čaj', locale: ' CS '));
        self::assertSame('maedchen', $this->slugger()->slug('Mädchen', locale: ' DE '));
    }

    public function testSlugForUnknownLocaleRemainsSafe(): void
    {
        $slug = $this->slugger()->slug('Unknown ☆ Čaj', locale: 'unknown');

        self::assertMatchesRegularExpression('/^[a-z0-9-]*$/', $slug);
        self::assertSame('unknown-caj', $slug);
    }

    public function testSupportedLocalesContainRepresentativeLanguageMaps(): void
    {
        $locales = $this->slugger()->supportedLocales();

        foreach (['cs', 'de', 'pl', 'ru', 'tr', 'vi'] as $locale) {
            self::assertContains($locale, $locales);
        }

        self::assertNotContains('latin', $locales);
        self::assertNotContains('latin_symbols', $locales);
    }

    private function slugger(): Slugger
    {
        return new Slugger();
    }
}
