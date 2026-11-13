<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Image;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\ScopeKind;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Filesystem\FilesystemServiceProvider;
use Lemonade\Framework\Image\ImageServiceProvider;
use Lemonade\Framework\Image\ImageVariantGenerator;
use Lemonade\Framework\Image\ImageVariantPathResolver;
use PHPUnit\Framework\TestCase;

final class ImageServiceProviderTest extends TestCase
{
    public function testPrivateImageServicesAreSharedWithoutRequestState(): void
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
        (new FilesystemServiceProvider())->register($container);
        (new ImageServiceProvider())->register($container);

        $resolver = $container->get(ImageVariantPathResolver::class);
        $generator = $container->get(ImageVariantGenerator::class);
        $scope = $container->beginScope(ScopeKind::Request);

        try {
            self::assertSame($resolver, $scope->get(ImageVariantPathResolver::class));
            self::assertSame($generator, $scope->get(ImageVariantGenerator::class));
        } finally {
            $scope->close();
        }
    }
}
