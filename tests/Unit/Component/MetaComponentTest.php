<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Component;

use DOMDocument;
use DOMXPath;
use Lemonade\Framework\Component\Meta\Config\MetaConfig;
use Lemonade\Framework\Component\Meta\MetaComponent;
use Lemonade\Framework\Component\Meta\MetaData;
use PHPUnit\Framework\TestCase;

final class MetaComponentTest extends TestCase
{
    public function testMetadataDefaultsKeepExplicitPageValues(): void
    {
        $data = (new MetaData(title: 'Article', titleSeparator: ' :: '))->withDefaults(
            websiteName: 'Lemonade',
            charset: 'UTF-8',
            viewport: 'width=device-width, initial-scale=1',
            rating: 'General',
            titleSeparator: ' | ',
        );

        self::assertSame('Article :: Lemonade', $data->getTitle());
        self::assertSame('UTF-8', $data->getCharset());
        self::assertSame('width=device-width, initial-scale=1', $data->getViewport());
        self::assertSame('General', $data->getRating());
    }

    public function testTypedSocialMetadataOverridesReservedCustomValuesWithoutDuplication(): void
    {
        $component = new MetaComponent(new MetaConfig(
            websiteName: 'Lemonade',
            charset: 'UTF-8',
            viewport: 'width=device-width, initial-scale=1',
            rating: 'General',
            titleSeparator: ' | ',
        ));

        $document = new DOMDocument();
        $document->loadHTML('<html><head>' . $component->render(new MetaData(
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
        )) . '</head></html>');
        $xpath = new DOMXPath($document);

        self::assertSame('https://example.test/canonical', $xpath->evaluate('string(//link[@rel="canonical"]/@href)'));
        foreach ([
            'og:url' => 'https://example.test/current?filter=active',
            'og:type' => 'article',
            'og:locale' => 'cs_CZ',
            'og:image:alt' => 'Image & "description"',
        ] as $property => $expected) {
            self::assertSame(1.0, $xpath->evaluate('count(//meta[@property="' . $property . '"])'));
            self::assertSame($expected, $xpath->evaluate('string(//meta[@property="' . $property . '"]/@content)'));
        }
        self::assertSame(1.0, $xpath->evaluate('count(//meta[@name="twitter:image:alt"])'));
        self::assertSame('Image & "description"', $xpath->evaluate('string(//meta[@name="twitter:image:alt"]/@content)'));
        self::assertSame('Example', $xpath->evaluate('string(//meta[@property="og:site_name"]/@content)'));
        self::assertSame('summary_large_image', $xpath->evaluate('string(//meta[@name="twitter:card"]/@content)'));
        self::assertSame('@example', $xpath->evaluate('string(//meta[@name="twitter:creator"]/@content)'));
        self::assertSame('123', $xpath->evaluate('string(//meta[@property="fb:app_id"]/@content)'));
    }

    public function testOpenGraphUrlAndTypeFallbackToCanonicalAndWebsite(): void
    {
        $component = new MetaComponent(new MetaConfig(
            websiteName: 'Lemonade',
            charset: 'UTF-8',
            viewport: 'width=device-width, initial-scale=1',
            rating: 'General',
            titleSeparator: ' | ',
        ));

        $document = new DOMDocument();
        $document->loadHTML('<html><head>' . $component->render(new MetaData(canonical: 'https://example.test/canonical?page=2')) . '</head></html>');
        $xpath = new DOMXPath($document);

        self::assertSame('https://example.test/canonical?page=2', $xpath->evaluate('string(//link[@rel="canonical"]/@href)'));
        self::assertSame('https://example.test/canonical?page=2', $xpath->evaluate('string(//meta[@property="og:url"]/@content)'));
        self::assertSame('website', $xpath->evaluate('string(//meta[@property="og:type"]/@content)'));
    }
}
