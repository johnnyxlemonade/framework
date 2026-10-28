<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Component\Breadcrumb;

use DOMDocument;
use DOMXPath;
use Lemonade\Framework\Component\Breadcrumb\BreadcrumbComponent;
use Lemonade\Framework\Component\Breadcrumb\BreadcrumbRenderer;
use Lemonade\Framework\Component\Breadcrumb\BreadcrumbServiceProvider;
use Lemonade\Framework\Container\Container;
use PHPUnit\Framework\TestCase;

final class BreadcrumbComponentTest extends TestCase
{
    public function testTrailPreservesItemOrderAndStartsEmpty(): void
    {
        $component = new BreadcrumbComponent(new BreadcrumbRenderer());
        $trail = $component->empty();

        self::assertSame(0, $trail->count());

        $trail
            ->add('Home', '/')
            ->add('Documentation', '/documentation');

        self::assertSame(2, $trail->count());
        self::assertSame('Home', $trail->items()[0]->label());
        self::assertSame('/documentation', $trail->items()[1]->url());
    }

    public function testRendererEscapesValuesAndMarksOnlyLastItemAsActive(): void
    {
        $trail = (new BreadcrumbComponent(new BreadcrumbRenderer()))
            ->empty()
            ->add('<Home &>', '/search?q=<term>&page=1')
            ->add('Current <page>', '/current');

        $html = (new BreadcrumbRenderer())->render($trail);

        $document = new DOMDocument();
        $previousErrorHandling = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrorHandling);
        $xpath = new DOMXPath($document);

        self::assertSame(0, $document->getElementsByTagName('home')->length);
        self::assertSame(0, $document->getElementsByTagName('term')->length);
        self::assertSame('<Home &>', $xpath->evaluate('string((//ol/li)[1]//span[@itemprop="name"])'));
        self::assertSame('/search?q=<term>&page=1', $xpath->evaluate('string((//ol/li)[1]/a/@href)'));
        self::assertSame('Current <page>', $xpath->evaluate('string((//ol/li)[2]/span[@itemprop="name"])'));
        self::assertSame(0.0, $xpath->evaluate('count((//ol/li)[2]/a)'));
        self::assertSame('page', $xpath->evaluate('string((//ol/li)[2]/span/@aria-current)'));
    }

    public function testServiceProviderResolvesComponentWithoutBreadcrumbConfig(): void
    {
        $container = new Container();
        (new BreadcrumbServiceProvider())->register($container);

        /** @var BreadcrumbComponent $component */
        $component = $container->get(BreadcrumbComponent::class);

        self::assertInstanceOf(BreadcrumbRenderer::class, $container->get(BreadcrumbRenderer::class));
        self::assertSame('', $component->render($component->empty()));
    }
}
