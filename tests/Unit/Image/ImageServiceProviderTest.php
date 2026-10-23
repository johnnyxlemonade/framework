<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Image;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\ScopeKind;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Image\Contract\ImageUrlResolverInterface;
use Lemonade\Framework\Image\ImageServiceProvider;
use Lemonade\Framework\Image\ImageViewHelper;
use PHPUnit\Framework\TestCase;

final class ImageServiceProviderTest extends TestCase
{
    public function testRelativePublicUrlServicesAreSharedWithoutRequestState(): void
    {
        $container = new Container();
        $container->singleton(
            ApplicationContext::class,
            new ApplicationContext(
                environment: Environment::Testing,
                paths: new Path(''),
                debug: DebugMode::enabled(),
            ),
        );
        (new ImageServiceProvider())->register($container);

        $resolver = $container->get(ImageUrlResolverInterface::class);
        $helper = $container->get(ImageViewHelper::class);
        $scope = $container->beginScope(ScopeKind::Request);

        try {
            self::assertSame($resolver, $scope->get(ImageUrlResolverInterface::class));
            self::assertSame($helper, $scope->get(ImageViewHelper::class));
        } finally {
            $scope->close();
        }
    }
}
