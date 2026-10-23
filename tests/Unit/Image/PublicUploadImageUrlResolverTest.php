<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Image;

use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Image\PublicUploadImageUrlResolver;
use Lemonade\Framework\Image\Value\ImageDimensions;
use Lemonade\Framework\Image\Value\ImageFormat;
use Lemonade\Framework\Image\Value\ImageReference;
use PHPUnit\Framework\TestCase;

final class PublicUploadImageUrlResolverTest extends TestCase
{
    public function testResolvesOneRootRelativeUploadPrefix(): void
    {
        $context = new ApplicationContext(
            Environment::Testing,
            new Path('/framework', '/framework/public'),
            DebugMode::disabled(),
        );
        $resolver = new PublicUploadImageUrlResolver($context);
        $reference = new ImageReference(
            'images/variants/a.webp',
            new ImageDimensions(1, 1),
            ImageFormat::Webp,
        );

        self::assertSame('/uploads/images/variants/a.webp', $resolver->url($reference));
    }
}
