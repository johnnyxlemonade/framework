<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Property\Support\Slug;

use Eris\Generators;
use Eris\TestTrait;
use Lemonade\Framework\Support\Slug\Slugger;
use PHPUnit\Framework\TestCase;

final class SluggerPropertiesTest extends TestCase
{
    use TestTrait;

    private const PROPERTY_CASES = 500;

    public function testSlugInvariantsHoldForRandomTextAndLengths(): void
    {
        $slugger = new Slugger();

        $this
            ->limitTo(self::PROPERTY_CASES)
            ->forAll(Generators::string(), Generators::choose(-10, 160))
            ->then(static function (string $text, int $maxLength) use ($slugger): void {
                $slug = $slugger->slug($text, maxLength: $maxLength);

                self::assertSlugInvariants($slugger, $slug, $maxLength);
            });
    }

    public function testSlugInvariantsHoldForHardUnicodeInput(): void
    {
        $slugger = new Slugger();

        $this
            ->limitTo(self::PROPERTY_CASES)
            ->forAll(
                Generators::elements([
                    '漢字かなカナ',
                    '안녕하세요',
                    '👩🏽‍💻🚀',
                    '𐍈𓀀',
                    '🏳️‍🌈',
                    'שלום',
                    '中文 😀 Ahoj',
                ]),
                Generators::choose(1, 160),
            )
            ->then(static function (string $text, int $maxLength) use ($slugger): void {
                $slug = $slugger->slug($text, maxLength: $maxLength);

                self::assertSlugInvariants($slugger, $slug, $maxLength);
            });
    }

    public function testSlugInvariantsHoldForAdversarialTextCorpus(): void
    {
        $slugger = new Slugger();

        $this
            ->limitTo(self::PROPERTY_CASES)
            ->forAll(Generators::elements(self::adversarialCorpus()), Generators::choose(-10, 160))
            ->then(static function (string $text, int $maxLength) use ($slugger): void {
                $slug = $slugger->slug($text, maxLength: $maxLength);

                self::assertSlugInvariants($slugger, $slug, $maxLength);
            });
    }

    public function testSlugInvariantsHoldForSupportedAndUnknownLocales(): void
    {
        $slugger = new Slugger();

        $this
            ->limitTo(self::PROPERTY_CASES)
            ->forAll(
                Generators::elements(self::adversarialCorpus()),
                Generators::elements(['cs', 'de', 'pl', 'ru', 'uk', 'tr', 'vi', 'ar', 'fa', 'sk', 'unknown-locale']),
                Generators::choose(-10, 160),
            )
            ->then(static function (string $text, string $locale, int $maxLength) use ($slugger): void {
                $slug = $slugger->slug($text, maxLength: $maxLength, locale: $locale);

                self::assertSlugInvariants($slugger, $slug, $maxLength);
            });
    }

    private static function assertSlugInvariants(Slugger $slugger, string $slug, int $maxLength): void
    {
        self::assertMatchesRegularExpression('/^[a-z0-9-]*$/', $slug);
        self::assertDoesNotMatchRegularExpression('/^-|-$|--/', $slug);
        self::assertLessThanOrEqual(max(0, $maxLength), strlen($slug));
        self::assertSame($slug, $slugger->slug($slug, $maxLength));

        if ($maxLength <= 0) {
            self::assertSame('', $slug);
        }
    }

    /**
     * @return list<string>
     */
    private static function adversarialCorpus(): array
    {
        return [
            '/////', '\\\\', 'a/b/c/d', 'a\\b\\c\\d',
            'https://example.com/test?a=1&b=2#anchor', '/admin/news/edit/123',
            '-----', '--------ahoj--------svete--------', '_____ahoj_____svete_____', '---___---___---',
            "Ahoj\t\n\r světe", "Ahoj\u{00A0}světe", "Ahoj\u{200B}světe", "e\u{0301} cafe",
            '👩🏽‍💻🚀🔥✅', '漢字かなカナ', '안녕하세요', 'שלום', 'العربية فارسی',
            'Привет мир', 'İstanbul ışık çığ', 'Zażółć gęślą jaźń', 'Žluťoučký kůň',
            '<script>alert(1)</script>', '../../etc/passwd', 'C:\\Windows\\System32\\drivers\\etc\\hosts',
            'název---s---milionem---pomlček', str_repeat('/', 1000), str_repeat('-', 1000), str_repeat('_', 1000),
            str_repeat(' Ahoj---světe / ', 300), str_repeat('🚀', 1000),
        ];
    }
}
