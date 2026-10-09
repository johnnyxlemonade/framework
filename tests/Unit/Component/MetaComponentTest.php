<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Component;

use Lemonade\Framework\Component\Meta\Config\MetaConfig;
use Lemonade\Framework\Component\Meta\MetaComponent;
use Lemonade\Framework\Component\Meta\MetaData;
use PHPUnit\Framework\TestCase;

final class MetaComponentTest extends TestCase
{
    public function testMakeAppliesTypedDefaults(): void
    {
        $component = new MetaComponent(new MetaConfig(
            websiteName: 'Lemonade',
            charset: 'UTF-8',
            viewport: 'width=device-width, initial-scale=1',
            rating: 'General',
            titleSeparator: ' | ',
        ));

        $meta = $component->make((new MetaData())->withTitleSeparator(' :: '));
        $html = $meta->toHtml();

        self::assertStringContainsString('charset="UTF-8"', $html);
        self::assertStringContainsString('content="width=device-width, initial-scale=1"', $html);
        self::assertStringContainsString('content="General"', $html);
        self::assertStringContainsString('content="Lemonade"', $html);
    }

    public function testRendersTypedOpenGraphMetadataWithoutReservedCustomDuplicates(): void
    {
        $component = new MetaComponent(new MetaConfig(
            websiteName: 'Lemonade',
            charset: 'UTF-8',
            viewport: 'width=device-width, initial-scale=1',
            rating: 'General',
            titleSeparator: ' | ',
        ));

        $html = $component->render(new MetaData(
            canonical: 'https://example.test/canonical',
            url: 'https://example.test/current?filter=active',
            type: 'article',
            locale: 'cs_CZ',
            image: 'https://example.test/image.jpg',
            imageAlt: 'Image & "description"',
            custom: [
                'og:url' => 'https://example.test/custom-url',
                'og:type' => 'video.other',
                'og:locale' => 'en_US',
                'og:image:alt' => 'Custom image alt',
                'twitter:image:alt' => 'Custom Twitter image alt',
                'og:site_name' => 'Example',
                'twitter:card' => 'summary_large_image',
                'twitter:creator' => '@example',
                'fb:app_id' => '123',
            ],
        ));

        self::assertStringContainsString('<link rel="canonical" href="https://example.test/canonical">', $html);
        self::assertStringContainsString('<meta property="og:url" content="https://example.test/current?filter=active">', $html);
        self::assertStringContainsString('<meta property="og:type" content="article">', $html);
        self::assertStringContainsString('<meta property="og:locale" content="cs_CZ">', $html);
        self::assertStringContainsString('<meta property="og:image:alt" content="Image &amp; &quot;description&quot;">', $html);
        self::assertStringContainsString('<meta name="twitter:image:alt" content="Image &amp; &quot;description&quot;">', $html);
        self::assertSame(1, substr_count($html, 'property="og:url"'));
        self::assertSame(1, substr_count($html, 'property="og:type"'));
        self::assertSame(1, substr_count($html, 'property="og:locale"'));
        self::assertSame(1, substr_count($html, 'property="og:image:alt"'));
        self::assertSame(1, substr_count($html, 'name="twitter:image:alt"'));
        self::assertStringContainsString('<meta property="og:site_name" content="Example">', $html);
        self::assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $html);
        self::assertStringContainsString('<meta name="twitter:creator" content="@example">', $html);
        self::assertStringContainsString('<meta property="fb:app_id" content="123">', $html);
    }

    public function testOpenGraphUrlFallsBackToCanonicalAndTypeFallsBackToWebsite(): void
    {
        $component = new MetaComponent(new MetaConfig(
            websiteName: 'Lemonade',
            charset: 'UTF-8',
            viewport: 'width=device-width, initial-scale=1',
            rating: 'General',
            titleSeparator: ' | ',
        ));

        $html = $component->render(new MetaData(canonical: 'https://example.test/canonical?page=2'));

        self::assertStringContainsString('<link rel="canonical" href="https://example.test/canonical?page=2">', $html);
        self::assertStringContainsString('<meta property="og:url" content="https://example.test/canonical?page=2">', $html);
        self::assertStringContainsString('<meta property="og:type" content="website">', $html);
    }
}
