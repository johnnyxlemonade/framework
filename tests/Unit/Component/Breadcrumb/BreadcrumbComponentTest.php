<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Component\Breadcrumb;

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

        self::assertStringContainsString('class="breadcrumb mb-0"', $html);
        self::assertStringContainsString('href="/search?q=&lt;term&gt;&amp;page=1"', $html);
        self::assertStringContainsString('&lt;Home &amp;&gt;', $html);
        self::assertStringContainsString('class="breadcrumb-item active"', $html);
        self::assertStringContainsString('aria-current="page"', $html);
        self::assertStringNotContainsString('href="/current"', $html);
        self::assertStringContainsString('content="1"', $html);
        self::assertStringContainsString('content="2"', $html);
        self::assertLessThan(
            strpos($html, 'content="2"'),
            strpos($html, 'content="1"'),
        );
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
